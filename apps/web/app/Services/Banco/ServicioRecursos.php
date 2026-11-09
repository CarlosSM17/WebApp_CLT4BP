<?php

namespace App\Services\Banco;

use App\Domain\Banco\CopiadorElementos;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\DesignElement;
use App\Models\DiffGroup;
use App\Models\DocumentFragment;
use App\Models\Item;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\Diseno\ServicioSincronizacion;
use App\Support\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reutiliza en un curso material creado en otro: copia un elemento del diseño con todo lo que cuelga de él, ítems o
 * documentos. La copia es independiente del original, queda como borrador y se vuelve a verificar y aprobar en su curso.
 */
class ServicioRecursos
{
    public function __construct(
        private readonly ServicioSincronizacion $sync,
        private readonly CopiadorElementos $copiador,
    ) {}

    /** Lo que se copiaría: el elemento, sus descendientes y los medios que citan. Las variantes son de los grupos de su curso. */
    public function arbol(DesignElement $raiz): Collection
    {
        $todos = DesignElement::where('course_id', $raiz->course_id)->whereNull('eliminado_at')->where('tipo', '!=', 'variante')->get();
        $arbol = collect([$raiz]);
        $frente = [$raiz->uid];
        while ($frente !== []) {
            $hijos = $todos->whereIn('padre_uid', $frente);
            $arbol = $arbol->concat($hijos);
            $frente = $hijos->pluck('uid')->all();
        }

        // Un medio no cuelga de nadie: se llega a él por las citas ![…](media:uid) de cualquier texto de las piezas
        preg_match_all('/\(media:([A-Za-z0-9_-]+)\)/', (string) json_encode($arbol->pluck('contenido')->all(), JSON_UNESCAPED_UNICODE), $citas);

        return $arbol->concat($todos->where('tipo', 'medio')->whereIn('uid', $citas[1]))->unique('uid')->values();
    }

    /**
     * @return array{uid: string, elementos: int, avisos: list<string>}
     */
    public function copiar(Course $destino, User $usuario, DesignElement $raiz, ?string $padreUid): array
    {
        $arbol = $this->arbol($raiz);
        $padre = $this->padreEnDestino($destino, $raiz, $padreUid);
        $copia = $this->copiador->copiar(
            $arbol->map(fn (DesignElement $e) => ['uid' => $e->uid, 'tipo' => $e->tipo, 'contenido' => json_decode(json_encode($e->contenido))])->all(),
            fn (string $prefijo) => $prefijo.'-'.Str::lower(Str::random(8)),
        );

        $avisos = [];
        DB::transaction(function () use ($destino, $usuario, $raiz, $arbol, $copia, $padre, &$avisos) {
            $this->copiarMedios($destino, $usuario, $arbol, $copia['uids'], $avisos);
            $grupos = DiffGroup::where('course_id', $destino->id)->pluck('clave')->all();

            foreach ($copia['elementos'] as $e) {
                $c = $e['contenido'];
                $esRaiz = $e['uid'] === $copia['uids'][$raiz->uid];
                if ($esRaiz) {
                    $this->colocar($destino, $raiz->tipo, $c, $padre);
                    $this->ligarObjetivos($destino, $raiz, $c, $avisos);
                }
                // Una ruta que no existe en este curso dejaría la tarea oculta para todos
                if ($e['tipo'] === 'tarea' && isset($c->rutas)) {
                    $c->rutas = array_values(array_intersect($c->rutas, $grupos));
                }
                if ($e['tipo'] === 'tarea' || $e['tipo'] === 'practica_parcial') {
                    if ($c->lenguaje !== $destino->lenguaje->value) {
                        $avisos[] = '«'.($c->titulo ?? $c->habilidad)."» está en {$c->lenguaje} y este curso es de {$destino->lenguaje->value}: ajusta el código.";
                    }
                }

                $r = $this->sync->aplicar($destino, $usuario, (object) ['uid' => $e['uid'], 'tipo' => $e['tipo'], 'base_version' => 0, 'contenido' => $c]);
                if ($r['estado'] !== 'ok') {
                    throw ValidationException::withMessages(['elemento_id' => 'No se pudo copiar un elemento: '.json_encode($r['errores'] ?? $r['estado'], JSON_UNESCAPED_UNICODE)]);
                }
            }
        });

        $omitidas = DesignElement::where('course_id', $raiz->course_id)->whereNull('eliminado_at')->where('tipo', 'variante')
            ->whereIn('padre_uid', $arbol->pluck('uid'))->count();
        if ($omitidas) {
            $avisos[] = "No se copiaron {$omitidas} variantes por grupo: son de los grupos del curso original.";
        }

        Auditoria::registrar('banco.recurso_copiado', $destino, ['origen' => $raiz->id, 'elementos' => count($copia['elementos'])]);

        return ['uid' => $copia['uids'][$raiz->uid], 'elementos' => count($copia['elementos']), 'avisos' => $avisos];
    }

    /**
     * @param  Collection<int, Item>  $items
     * @return array{nuevos: int}
     */
    public function copiarItems(Course $destino, Collection $items): array
    {
        foreach ($items as $i) {
            Item::create([
                'course_id' => $destino->id,
                'objetivo' => null, // los códigos de objetivo son de cada curso
                'tipo' => $i->tipo,
                'nivel' => $i->nivel,
                'enunciado' => $i->enunciado,
                'clave' => $i->clave,
                'lenguaje' => $i->tipo === 'programacion' ? $destino->lenguaje->value : null,
                'casos_prueba' => $i->casos_prueba,
                'solucion' => $i->solucion,
                'autor_tipo' => $i->autor_tipo,
                'estado' => 'borrador',
            ]);
        }
        Auditoria::registrar('banco.items_reutilizados', $destino, ['ids' => $items->pluck('id')->all()]);

        return ['nuevos' => $items->count()];
    }

    /** Copia un documento ya procesado con sus fragmentos: no hace falta volver a calcular los embeddings. */
    public function copiarDocumento(Course $destino, User $usuario, CourseDocument $original): CourseDocument
    {
        abort_if($original->estado !== 'listo', 422, 'Ese documento todavía no está listo.');
        abort_if(
            CourseDocument::where('course_id', $destino->id)->where('sha256', $original->sha256)->exists(),
            409, 'Ese documento ya está en el material del curso.'
        );

        $extension = pathinfo($original->ruta, PATHINFO_EXTENSION);
        $ruta = "cursos/{$destino->id}/material/".Str::uuid().'.'.$extension;
        abort_unless(Storage::disk('local')->copy($original->ruta, $ruta), 422, 'No se encontró el archivo original.');

        $copia = DB::transaction(function () use ($destino, $usuario, $original, $ruta) {
            $copia = CourseDocument::create([
                ...$original->only('titulo', 'nombre_original', 'mime', 'bytes', 'sha256', 'estado', 'fragmentos', 'modelo_embeddings'),
                'course_id' => $destino->id,
                'ruta' => $ruta,
                'subido_por' => $usuario->id,
            ]);
            $tabla = (new DocumentFragment)->getTable();
            DB::statement(
                "insert into {$tabla} (course_document_id, course_id, orden, pagina, texto, embedding, created_at, updated_at)
                 select ?, ?, orden, pagina, texto, embedding, now(), now() from {$tabla} where course_document_id = ?",
                [$copia->id, $destino->id, $original->id],
            );

            return $copia;
        });
        Auditoria::registrar('banco.documento_reutilizado', $copia, ['origen' => $original->id]);

        return $copia;
    }

    /** Una tarea, un soporte o una ayuda necesitan dónde colgarse en el curso de destino. */
    private function padreEnDestino(Course $destino, DesignElement $raiz, ?string $padreUid): ?DesignElement
    {
        $tiposPadre = match ($raiz->tipo) {
            'tarea', 'soporte' => ['clase'],
            'procedimental' => ['clase', 'tarea'],
            default => null,
        };
        if ($tiposPadre === null) {
            return null;
        }

        $padre = DesignElement::where('course_id', $destino->id)->where('uid', (string) $padreUid)
            ->whereNull('eliminado_at')->whereIn('tipo', $tiposPadre)->first();
        if (! $padre) {
            throw ValidationException::withMessages(['padre_uid' => $raiz->tipo === 'procedimental'
                ? 'Elige la clase o la tarea a la que se agrega esta ayuda.'
                : 'Elige la clase a la que se agrega.']);
        }

        return $padre;
    }

    private function colocar(Course $destino, string $tipo, object $c, ?DesignElement $padre): void
    {
        if ($tipo === 'clase') {
            $c->orden = 1 + (int) DesignElement::where('course_id', $destino->id)->where('tipo', 'clase')->whereNull('eliminado_at')->max('orden');
        }
        if ($tipo === 'tarea') {
            $c->clase_uid = $padre->uid;
            $c->orden = 1 + (int) DesignElement::where('course_id', $destino->id)->where('tipo', 'tarea')
                ->where('padre_uid', $padre->uid)->whereNull('eliminado_at')->max('orden');
        }
        if ($tipo === 'soporte') {
            $c->clase_uid = $padre->uid;
        }
        if ($tipo === 'procedimental') {
            unset($c->tarea_uid, $c->clase_uid);
            $c->{$padre->tipo === 'clase' ? 'clase_uid' : 'tarea_uid'} = $padre->uid;
        }
    }

    /**
     * Los códigos de objetivo son de cada curso. Una clase se liga a los objetivos de destino con la misma descripción;
     * los demás se quitan con aviso, porque un código ajeno apuntaría al objetivo equivocado.
     *
     * @param  list<string>  $avisos
     */
    private function ligarObjetivos(Course $destino, DesignElement $raiz, object $c, array &$avisos): void
    {
        if ($raiz->tipo !== 'clase') {
            return;
        }
        $delOrigen = DesignElement::where('course_id', $raiz->course_id)->where('tipo', 'objetivo')->whereNull('eliminado_at')->get()
            ->mapWithKeys(fn (DesignElement $o) => [$o->contenido->codigo => $o->contenido->descripcion]);
        $delDestino = DesignElement::where('course_id', $destino->id)->where('tipo', 'objetivo')->whereNull('eliminado_at')->get()
            ->mapWithKeys(fn (DesignElement $o) => [$o->contenido->descripcion => $o->contenido->codigo]);

        $ligados = [];
        foreach ($c->objetivos as $codigo) {
            $nuevo = $delDestino[$delOrigen[$codigo] ?? ''] ?? null;
            if ($nuevo) {
                $ligados[] = $nuevo;
            } else {
                $avisos[] = "El objetivo {$codigo} no existe en este curso: liga la clase «{$c->titulo}» a sus objetivos.";
            }
        }
        $c->objetivos = $ligados;
    }

    /**
     * @param  Collection<int, DesignElement>  $arbol
     * @param  array<string, string>  $uids
     * @param  list<string>  $avisos
     */
    private function copiarMedios(Course $destino, User $usuario, Collection $arbol, array $uids, array &$avisos): void
    {
        foreach ($arbol->where('tipo', 'medio') as $medio) {
            $asset = MediaAsset::where('course_id', $medio->course_id)->where('uid', $medio->uid)->first();
            if (! $asset) {
                $avisos[] = "El medio «{$medio->contenido->titulo}» no tiene archivo en el servidor: súbelo de nuevo.";

                continue;
            }
            $uid = $uids[$medio->uid];
            $ruta = "cursos/{$destino->id}/medios/{$uid}.".pathinfo($asset->ruta, PATHINFO_EXTENSION);
            if (! Storage::disk('local')->copy($asset->ruta, $ruta)) {
                $avisos[] = "No se encontró el archivo del medio «{$medio->contenido->titulo}»: súbelo de nuevo.";

                continue;
            }
            MediaAsset::create([...$asset->only('mime', 'bytes', 'sha256'), 'course_id' => $destino->id, 'uid' => $uid, 'ruta' => $ruta, 'subido_por' => $usuario->id]);
        }
    }
}
