<?php

namespace App\Services\Banco;

use App\Models\BankTopic;
use App\Models\Course;
use App\Models\DesignElement;
use App\Models\User;
use App\Services\Diseno\ServicioSincronizacion;
use App\Support\Auditoria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lleva una ruta de aprendizaje al diseño del curso: un objetivo por tema y, si se pide, una clase de tareas vacía por
 * tema, en el orden de la ruta. Pasa por la misma puerta que la consola (ServicioSincronizacion), así que el contenido
 * se valida contra los contratos, queda como borrador y la consola lo recibe al sincronizar.
 */
class ServicioRuta
{
    public function __construct(private readonly ServicioSincronizacion $sync) {}

    /**
     * @param  list<BankTopic>  $temas  en el orden de la ruta
     * @return array{objetivos: int, clases: int, reutilizados: int}
     */
    public function aplicar(Course $curso, User $usuario, array $temas, bool $crearClases): array
    {
        $resultado = DB::transaction(function () use ($curso, $usuario, $temas, $crearClases) {
            $existentes = DesignElement::where('course_id', $curso->id)->whereNull('eliminado_at')
                ->whereIn('tipo', ['objetivo', 'clase'])->get();
            $objetivos = $existentes->where('tipo', 'objetivo');
            $clases = $existentes->where('tipo', 'clase');

            // Sigue la numeración del curso: nunca se reutiliza un código ni se pisa un objetivo que ya exista
            $siguiente = 1 + (int) $objetivos->map(fn (DesignElement $o) => (int) Str::after($o->contenido->codigo, 'OB-'))->max();
            $ordenClase = 1 + (int) $clases->max('orden');
            $conteo = ['objetivos' => 0, 'clases' => 0, 'reutilizados' => 0];

            foreach ($temas as $tema) {
                $objetivo = $objetivos->first(fn (DesignElement $o) => $o->contenido->descripcion === $tema->descripcion);
                if ($objetivo) {
                    $codigo = $objetivo->contenido->codigo;
                    $conteo['reutilizados']++;
                } else {
                    $codigo = 'OB-'.$siguiente;
                    $this->crear($curso, $usuario, 'objetivo', 'ob', [
                        'codigo' => $codigo,
                        'orden' => $siguiente++,
                        'descripcion' => $tema->descripcion,
                        'tipo' => $tema->tipo,
                        'evaluacion' => $tema->evaluacion,
                    ]);
                    $conteo['objetivos']++;
                }

                if ($crearClases && ! $clases->contains(fn (DesignElement $c) => $c->contenido->titulo === $tema->titulo)) {
                    $this->crear($curso, $usuario, 'clase', 'tc', [
                        'titulo' => $tema->titulo,
                        'descripcion_complejidad' => (string) $tema->complejidad,
                        'orden' => $ordenClase++,
                        'objetivos' => [$codigo],
                        'diseno' => [
                            'paso_clt4bp' => 5,
                            'componente' => 'tarea',
                            'efectos' => [],
                            'interactividad' => 'media',
                            'tiempo_estimado_min' => max(1, $tema->horas * 60),
                            'justificacion' => 'Tema tomado del banco de objetivos, según la ruta de aprendizaje propuesta.',
                            'advertencias' => [],
                        ],
                    ]);
                    $conteo['clases']++;
                }
            }

            return $conteo;
        });

        Auditoria::registrar('banco.ruta_aplicada', $curso, ['temas' => array_map(fn (BankTopic $t) => $t->clave, $temas), ...$resultado]);

        return $resultado;
    }

    /** @param array<string, mixed> $contenido */
    private function crear(Course $curso, User $usuario, string $tipo, string $prefijo, array $contenido): void
    {
        $uid = $prefijo.'-'.Str::lower(Str::random(8));
        $objeto = json_decode(json_encode(['uid' => $uid, ...$contenido]));

        $r = $this->sync->aplicar($curso, $usuario, (object) ['uid' => $uid, 'tipo' => $tipo, 'base_version' => 0, 'contenido' => $objeto]);
        if ($r['estado'] !== 'ok') {
            throw ValidationException::withMessages(['temas' => 'No se pudo crear «'.($contenido['titulo'] ?? $contenido['codigo']).'»: '.json_encode($r['errores'] ?? $r['estado'], JSON_UNESCAPED_UNICODE)]);
        }
    }
}
