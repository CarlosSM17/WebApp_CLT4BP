<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\DesignElement;
use App\Models\Item;
use App\Models\User;
use App\Services\Banco\ServicioRecursos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Banco de recursos: el material ya creado en los cursos que el instructor imparte, listo para reutilizar. */
class BancoRecursoController extends Controller
{
    private const TIPOS = ['clase', 'tarea', 'soporte', 'procedimental', 'practica_parcial', 'medio'];

    /** GET /banco/recursos?excluir_curso=1&tipo=tarea&lenguaje=c&q=punteros */
    public function index(Request $request): JsonResponse
    {
        $f = $this->filtros($request);

        $elementos = DesignElement::with('course:id,titulo,lenguaje')->whereIn('course_id', $this->cursos($request->user(), $f))
            ->whereNull('eliminado_at')->whereIn('tipo', isset($f['tipo']) ? [$f['tipo']] : self::TIPOS)
            ->latest('updated_at')->limit(500)->get();

        $hijos = DesignElement::whereIn('course_id', $elementos->pluck('course_id')->unique())->whereNull('eliminado_at')
            ->where('tipo', '!=', 'variante')->whereNotNull('padre_uid')
            ->get(['course_id', 'padre_uid'])->countBy(fn (DesignElement $e) => $e->course_id.'/'.$e->padre_uid);

        $filas = $elementos->map(fn (DesignElement $e) => [
            'id' => $e->id,
            'tipo' => $e->tipo,
            'titulo' => $this->titulo($e),
            'estado' => $e->estado,
            'autor_tipo' => $e->autor_tipo,
            'piezas' => $hijos[$e->course_id.'/'.$e->uid] ?? 0,
            'curso' => $e->course->only('id', 'titulo'),
            'actualizado_at' => $e->updated_at,
        ]);

        if ($q = mb_strtolower($f['q'] ?? '')) {
            $filas = $filas->filter(fn (array $r) => str_contains(mb_strtolower($r['titulo']), $q) || str_contains(mb_strtolower($r['curso']['titulo']), $q));
        }

        return response()->json(['data' => $filas->values()]);
    }

    /** GET /banco/recursos/{elemento}: el elemento y las piezas que se copiarían con él, para revisarlo antes. */
    public function show(DesignElement $elemento, ServicioRecursos $servicio): JsonResponse
    {
        Gate::authorize('view', $elemento->course);
        abort_unless(in_array($elemento->tipo, self::TIPOS, true) && ! $elemento->eliminado_at, 404);

        return response()->json(['data' => $servicio->arbol($elemento)->map(fn (DesignElement $e) => [
            'uid' => $e->uid,
            'tipo' => $e->tipo,
            'titulo' => $this->titulo($e),
            'estado' => $e->estado,
            'contenido' => $e->contenido,
        ])]);
    }

    /** POST /courses/{course}/banco/recursos {elemento_id, padre_uid?}: copia el elemento con sus piezas al curso. */
    public function copiar(Request $request, Course $course, ServicioRecursos $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        $datos = $request->validate([
            'elemento_id' => ['required', 'integer', 'exists:design_elements,id'],
            'padre_uid' => ['nullable', 'string', 'max:64'],
        ]);
        $elemento = DesignElement::findOrFail($datos['elemento_id']);
        Gate::authorize('view', $elemento->course);
        abort_unless(in_array($elemento->tipo, self::TIPOS, true) && ! $elemento->eliminado_at, 422, 'Ese elemento no se puede reutilizar.');

        return response()->json(['data' => $servicio->copiar($course, $request->user(), $elemento, $datos['padre_uid'] ?? null)], 201);
    }

    /** GET /banco/items-cursos?excluir_curso=1&lenguaje=c: ítems creados en otros cursos. */
    public function items(Request $request): JsonResponse
    {
        $f = $this->filtros($request);
        $items = Item::with('course:id,titulo')->whereIn('course_id', $this->cursos($request->user(), $f))
            ->whereNull('bank_item_id') // los del banco ya se ofrecen en el banco
            ->latest('id')->limit(500)->get();

        return response()->json(['data' => $items->map(fn (Item $i) => [
            'id' => $i->id,
            'tipo' => $i->tipo,
            'nivel' => $i->nivel,
            'objetivo' => $i->objetivo,
            'estado' => $i->estado,
            'enunciado' => $i->enunciado,
            'curso' => $i->course->only('id', 'titulo'),
        ])]);
    }

    /** POST /courses/{course}/banco/items-cursos {ids}: copia ítems de otros cursos, como borrador. */
    public function copiarItems(Request $request, Course $course, ServicioRecursos $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        $ids = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:200'], 'ids.*' => ['integer', 'distinct']])['ids'];
        $items = Item::whereIn('id', $ids)->get();
        abort_if($items->count() !== count($ids), 422, 'Alguno de los ítems ya no existe.');
        foreach ($items->pluck('course_id')->unique() as $origen) {
            Gate::authorize('view', Course::findOrFail($origen));
        }

        return response()->json(['data' => $servicio->copiarItems($course, $items)], 201);
    }

    /** GET /banco/documentos?excluir_curso=1: material procesado de otros cursos. */
    public function documentos(Request $request): JsonResponse
    {
        $f = $this->filtros($request);
        $documentos = CourseDocument::with('curso:id,titulo')->whereIn('course_id', $this->cursos($request->user(), $f))
            ->where('estado', 'listo')->latest('id')->limit(200)->get();

        return response()->json(['data' => $documentos->map(fn (CourseDocument $d) => [
            ...$d->only('id', 'titulo', 'nombre_original', 'bytes', 'fragmentos'),
            'curso' => $d->curso->only('id', 'titulo'),
        ])]);
    }

    /** POST /courses/{course}/banco/documentos/{document} */
    public function copiarDocumento(Request $request, Course $course, CourseDocument $document, ServicioRecursos $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        Gate::authorize('view', $document->curso);
        $copia = $servicio->copiarDocumento($course, $request->user(), $document);

        return response()->json(['data' => $copia->only('id', 'titulo', 'estado', 'fragmentos')], 201);
    }

    private function filtros(Request $request): array
    {
        return $request->validate([
            'excluir_curso' => ['nullable', 'integer'],
            'tipo' => ['nullable', Rule::in(self::TIPOS)],
            'lenguaje' => ['nullable', Rule::in(['c', 'cpp', 'python'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
    }

    /** Lo que se puede reutilizar: los cursos que el instructor imparte (todos, si es administrador; igual que CoursePolicy::view). */
    private function cursos(User $user, array $f): array
    {
        return Course::query()
            ->unless($user->hasRole(Rol::Admin->value), fn ($q) => $q->whereHas('instructores', fn ($i) => $i->whereKey($user->id)))
            ->when($f['excluir_curso'] ?? null, fn ($q, $id) => $q->whereKeyNot($id))
            ->when($f['lenguaje'] ?? null, fn ($q, $l) => $q->where('lenguaje', $l))
            ->pluck('id')->all();
    }

    private function titulo(DesignElement $e): string
    {
        $c = $e->contenido;

        return (string) ($c->titulo ?? $c->habilidad ?? $e->uid);
    }
}
