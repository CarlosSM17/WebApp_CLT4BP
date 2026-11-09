<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banco\SelectorItems;
use App\Http\Controllers\Controller;
use App\Models\BankItem;
use App\Models\Course;
use App\Models\Item;
use App\Services\Banco\ServicioBancoItems;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Banco de preguntas y problemas: consultar, pasar al curso y armar pruebas diagnósticas. */
class BancoItemController extends Controller
{
    /** GET /banco/items?lenguaje=c&curso=1: el instructor sí ve clave y solución; `curso` marca lo que ese curso ya tiene. */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'lenguaje' => ['nullable', Rule::in(['c', 'cpp', 'python'])],
            'curso' => ['nullable', 'integer', 'exists:courses,id'],
        ]);

        $consulta = BankItem::with('tema:id,clave')->orderBy('id');
        if ($datos['lenguaje'] ?? null) {
            $consulta->paraLenguaje($datos['lenguaje']);
        }

        $enCurso = [];
        if ($datos['curso'] ?? null) {
            Gate::authorize('view', Course::findOrFail($datos['curso']));
            $enCurso = Item::where('course_id', $datos['curso'])->whereNotNull('bank_item_id')->pluck('bank_item_id')->flip()->all();
        }

        return response()->json(['data' => $consulta->get()->map(fn (BankItem $b) => [
            'id' => $b->id,
            'codigo' => $b->codigo,
            'tema' => $b->tema->clave,
            'tipo' => $b->tipo,
            'nivel' => $b->nivel,
            'lenguajes' => $b->lenguajes,
            'enunciado' => $b->enunciado,
            'clave' => $b->clave,
            'casos_prueba' => $b->casos_prueba,
            'solucion' => $b->solucion,
            'en_curso' => isset($enCurso[$b->id]),
        ])]);
    }

    /** POST /courses/{course}/banco/items {ids}: copia al curso, como borrador. */
    public function agregar(Request $request, Course $course, ServicioBancoItems $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        return response()->json(['data' => $servicio->agregar($course, $this->delLenguaje($course, $ids))]);
    }

    /**
     * POST /courses/{course}/banco/seleccion {tipo, temas, por_tema}: propone qué ítems usar. No crea nada: el instructor
     * revisa y ajusta la selección antes de crear la prueba.
     */
    public function seleccion(Request $request, Course $course, SelectorItems $selector): JsonResponse
    {
        Gate::authorize('update', $course);
        $datos = $request->validate([
            'tipo' => ['required', Rule::in(['teorica', 'practica'])],
            'temas' => ['required', 'array', 'min:1', 'max:100'],
            'temas.*' => ['string', 'max:64'],
            'por_tema' => ['required', 'integer', 'between:1,20'],
        ]);

        $candidatos = BankItem::with('tema:id,clave')->paraLenguaje($this->lenguaje($course))
            ->when($datos['tipo'] === 'practica', fn ($q) => $q->where('nivel', 'practica'), fn ($q) => $q->where('nivel', '!=', 'practica'))
            ->get()->map(fn (BankItem $b) => ['id' => $b->id, 'tema' => $b->tema->clave, 'tipo' => $b->tipo])->all();

        // Lo que ya salió en otra prueba del curso no se repite: la forma B no lleva las preguntas de la forma A
        $usados = DB::table('assessment_items')->join('items', 'items.id', '=', 'assessment_items.item_id')
            ->where('items.course_id', $course->id)->whereNotNull('items.bank_item_id')
            ->pluck('items.bank_item_id')->map(fn ($id) => (int) $id)->all();

        return response()->json(['data' => $selector->elegir($candidatos, $datos['temas'], $datos['por_tema'], $usados)]);
    }

    /** POST /courses/{course}/banco/prueba: crea la prueba con los ítems elegidos del banco. */
    public function prueba(Request $request, Course $course, ServicioBancoItems $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:160'],
            'momento' => ['required', Rule::in(['pre', 'post'])],
            'tipo' => ['required', Rule::in(['teorica', 'practica'])],
            'forma' => ['required', Rule::in(['A', 'B'])],
            'tiempo_limite_min' => ['nullable', 'integer', 'between:5,240'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.puntos' => ['required', 'numeric', 'between:0.5,100'],
        ]);
        $this->delLenguaje($course, array_column($datos['items'], 'id'));

        $prueba = $servicio->crearPrueba($course, collect($datos)->except('items')->all(), array_values($datos['items']));

        return response()->json(['data' => $prueba->loadCount('items')], 201);
    }

    /** Los ítems pedidos, comprobando que existan y sirvan para el lenguaje del curso. */
    private function delLenguaje(Course $curso, array $ids): Collection
    {
        $items = BankItem::with('tema')->whereIn('id', $ids)->get();
        abort_if($items->count() !== count(array_unique($ids)), 422, 'Alguno de los ítems ya no está en el banco.');
        abort_if(
            $items->contains(fn (BankItem $b) => ! $b->sirveEn($this->lenguaje($curso))),
            422, 'Alguno de los ítems no es para el lenguaje de este curso.'
        );

        return $items;
    }

    private function lenguaje(Course $curso): string
    {
        return $curso->lenguaje->value;
    }
}
