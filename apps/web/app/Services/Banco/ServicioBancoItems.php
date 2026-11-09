<?php

namespace App\Services\Banco;

use App\Models\Assessment;
use App\Models\BankItem;
use App\Models\Course;
use App\Models\DesignElement;
use App\Models\Item;
use App\Services\Codigo\EvaluadorCasos;
use App\Support\Auditoria;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Pasa ítems del banco a un curso: copias editables, una sola por ítem del banco. */
class ServicioBancoItems
{
    // Crear una prueba ejecuta las soluciones en el momento: esto acota cuánto puede tardar (la consola espera 3 minutos)
    private const MAX_PROBLEMAS = 20;

    private const SEGUNDOS_VERIFICACION = 170;

    public function __construct(private readonly EvaluadorCasos $evaluador) {}

    /**
     * Copia al curso lo que aún no tenga, como borrador para que el instructor lo revise.
     *
     * @param  Collection<int, BankItem>  $bancoItems
     * @return array{nuevos: int, ya_estaban: int}
     */
    public function agregar(Course $curso, Collection $bancoItems): array
    {
        $nuevos = $bancoItems->filter(fn (BankItem $b) => $this->copia($curso, $b)->wasRecentlyCreated)->count();
        Auditoria::registrar('banco.items_agregados', $curso, ['codigos' => $bancoItems->pluck('codigo')->all()]);

        return ['nuevos' => $nuevos, 'ya_estaban' => $bancoItems->count() - $nuevos];
    }

    /**
     * Crea una prueba con ítems del banco. Quedan aprobados por el instructor al elegirlos, con la misma regla de
     * siempre: un problema de programación se aprueba solo si su solución pasa sus casos.
     *
     * @param  array<string, mixed>  $datos  nombre, momento, tipo, forma, tiempo_limite_min
     * @param  list<array{id: int, puntos: float|int}>  $elegidos
     */
    public function crearPrueba(Course $curso, array $datos, array $elegidos): Assessment
    {
        $bancoItems = BankItem::with('tema')->whereIn('id', array_column($elegidos, 'id'))->get()->keyBy('id');

        if ($bancoItems->where('tipo', 'programacion')->count() > self::MAX_PROBLEMAS) {
            throw ValidationException::withMessages(['items' => 'Una prueba admite hasta '.self::MAX_PROBLEMAS.' problemas de programación.']);
        }
        foreach ($elegidos as $e) {
            $practico = $bancoItems[$e['id']]->nivel === 'practica';
            if ($practico !== ($datos['tipo'] === 'practica')) {
                throw ValidationException::withMessages(['items' => $datos['tipo'] === 'practica'
                    ? 'Una prueba práctica solo lleva problemas de programación.'
                    : 'Una prueba teórica no lleva problemas de programación.']);
            }
        }

        // Fuera de la transacción: ejecutar los casos tarda y no debe bloquear las escrituras del curso
        $copias = collect($elegidos)->map(fn (array $e) => $this->copia($curso, $bancoItems[$e['id']]));
        $this->verificar($curso, $copias);

        $prueba = DB::transaction(function () use ($datos, $curso, $elegidos, $copias) {
            $prueba = Assessment::create([...$datos, 'course_id' => $curso->id]);
            foreach (array_values($elegidos) as $i => $e) {
                $copias[$i]->update(['estado' => 'aprobado']);
                $prueba->items()->attach($copias[$i]->id, ['orden' => $i + 1, 'puntos' => $e['puntos']]);
            }

            return $prueba;
        });
        Auditoria::registrar('prueba.creada', $prueba, ['desde' => 'banco']);

        return $prueba;
    }

    private function copia(Course $curso, BankItem $b): Item
    {
        return Item::firstOrCreate(
            ['course_id' => $curso->id, 'bank_item_id' => $b->id],
            [
                'objetivo' => $this->objetivoDelCurso($curso, $b),
                'tipo' => $b->tipo,
                'nivel' => $b->nivel,
                'enunciado' => $b->enunciado,
                'clave' => $b->clave,
                'lenguaje' => $b->tipo === 'programacion' ? $curso->lenguaje->value : null,
                'casos_prueba' => $b->casos_prueba,
                'solucion' => $b->solucion,
                'autor_tipo' => 'humano',
                'estado' => 'borrador',
            ],
        );
    }

    /** Si el curso ya tomó el tema como objetivo (misma descripción), el ítem queda ligado a su código. */
    private function objetivoDelCurso(Course $curso, BankItem $b): ?string
    {
        return DesignElement::where('course_id', $curso->id)->where('tipo', 'objetivo')->whereNull('eliminado_at')
            ->get()->first(fn (DesignElement $o) => $o->contenido->descripcion === $b->tema->descripcion)?->contenido->codigo;
    }

    /**
     * Ejecuta las soluciones que falten. Cada resultado se guarda al momento: si algo falla o el instructor reintenta,
     * lo ya verificado no se vuelve a ejecutar.
     *
     * @param  Collection<int, Item>  $copias
     */
    private function verificar(Course $curso, Collection $copias): void
    {
        $pendientes = $copias->where('tipo', 'programacion')->whereNull('verificado_at');
        if ($pendientes->isNotEmpty()) {
            set_time_limit(self::SEGUNDOS_VERIFICACION); // en Windows el límite de PHP cuenta también la espera al ejecutor
        }
        foreach ($pendientes as $copia) {
            try {
                $r = $this->evaluador->evaluar($curso->lenguaje->value, (string) $copia->solucion, $copia->casos_prueba ?? []);
            } catch (Throwable) {
                throw ValidationException::withMessages(['items' => 'No se pudieron ejecutar los problemas de programación: revisa que el ejecutor de código esté encendido.']);
            }
            if ($r['total'] === 0 || $r['aprobados'] !== $r['total']) {
                throw ValidationException::withMessages(['items' => 'Una solución no pasa sus casos de prueba («'.Str::limit($copia->enunciado['md'], 60).'»): revísala en el Banco de ítems del curso.']);
            }
            $copia->update(['verificado_at' => now()]);
        }
    }
}
