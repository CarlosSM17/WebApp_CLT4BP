<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Banco\RutaAprendizaje;
use App\Http\Controllers\Controller;
use App\Models\BankTopic;
use App\Models\Course;
use App\Models\User;
use App\Services\Banco\ServicioRuta;
use App\Support\Auditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Banco de objetivos de aprendizaje: el catálogo, los propios del instructor y la ruta que se propone con ellos. */
class BancoTemaController extends Controller
{
    /** GET /banco/temas?lenguaje=c: el catálogo y los propios, con cuántos ítems tiene cada uno en ese lenguaje. */
    public function index(Request $request): JsonResponse
    {
        $lenguaje = $request->validate(['lenguaje' => ['nullable', Rule::in(['c', 'cpp', 'python'])]])['lenguaje'] ?? null;

        $temas = BankTopic::visiblesPara($request->user())
            ->withCount(['items' => fn ($q) => $lenguaje ? $q->paraLenguaje($lenguaje) : $q])
            ->orderBy('orden')->orderBy('id')->get()
            ->filter(fn (BankTopic $t) => ! $lenguaje || $t->sirveEn($lenguaje))
            ->map(fn (BankTopic $t) => $this->paraConsola($t))->values();

        return response()->json(['data' => $temas]);
    }

    /** POST /banco/temas: un objetivo propio, que queda disponible para todos los cursos del instructor. */
    public function store(Request $request): JsonResponse
    {
        $datos = $this->validar($request);
        $this->sinCiclo($request->user(), 'nuevo', $datos['prerrequisitos']);

        $tema = BankTopic::create([
            ...$datos,
            'clave' => 'propio-'.Str::lower(Str::random(12)),
            'owner_id' => $request->user()->id,
            'lenguajes' => [],
            // Va al final de lo que tenga por requisito: los requisitos mandan y esto solo desempata
            'orden' => 1000,
        ]);
        Auditoria::registrar('banco.tema_creado', $tema);

        return response()->json(['data' => $this->paraConsola($tema)], 201);
    }

    public function update(Request $request, BankTopic $tema): JsonResponse
    {
        Gate::authorize('update', $tema);
        $datos = $this->validar($request);
        $this->sinCiclo($request->user(), $tema->clave, $datos['prerrequisitos']);
        $tema->update($datos);

        return response()->json(['data' => $this->paraConsola($tema)]);
    }

    public function destroy(BankTopic $tema): Response
    {
        Gate::authorize('delete', $tema);
        $tema->delete();
        Auditoria::registrar('banco.tema_eliminado', $tema);

        return response()->noContent();
    }

    /** POST /banco/ruta {temas}: propone la ruta para los temas elegidos, con los prerrequisitos que hagan falta. */
    public function ruta(Request $request, RutaAprendizaje $ruta): JsonResponse
    {
        $elegidos = $this->validarClaves($request, 'temas');
        $visibles = $this->visibles($request->user());
        $propuesta = $ruta->proponer($this->grafo($visibles), $elegidos);

        return response()->json(['data' => [
            'ruta' => $this->pasos($propuesta['orden'], $visibles, $propuesta['agregados']),
            'avisos' => [],
        ]]);
    }

    /** POST /banco/ruta/avisos {orden}: revisa un orden ajustado a mano. Avisa, pero el instructor decide. */
    public function avisos(Request $request, RutaAprendizaje $ruta): JsonResponse
    {
        $orden = $this->validarClaves($request, 'orden');

        return response()->json(['data' => $ruta->avisos($this->grafo($this->visibles($request->user())), $orden)]);
    }

    /** POST /courses/{course}/banco/ruta {orden, crear_clases}: pasa la ruta al diseño del curso. */
    public function aplicar(Request $request, Course $course, ServicioRuta $servicio): JsonResponse
    {
        Gate::authorize('update', $course);
        $orden = $this->validarClaves($request, 'orden');
        $crearClases = $request->validate(['crear_clases' => ['required', 'boolean']])['crear_clases'];

        $visibles = $this->visibles($request->user());
        $temas = [];
        foreach ($orden as $clave) {
            $temas[] = $visibles[$clave] ?? throw ValidationException::withMessages(['orden' => "No existe el tema {$clave}."]);
        }

        return response()->json(['data' => $servicio->aplicar($course, $request->user(), $temas, $crearClases)], 201);
    }

    /** @return Collection<string, BankTopic> */
    private function visibles(User $user): Collection
    {
        return BankTopic::visiblesPara($user)->get()->keyBy('clave');
    }

    /** @return list<string> */
    private function validarClaves(Request $request, string $campo): array
    {
        return array_values($request->validate([
            $campo => ['required', 'array', 'min:1', 'max:100'],
            "{$campo}.*" => ['string', 'max:64', 'distinct'],
        ])[$campo]);
    }

    /**
     * @param  Collection<string, BankTopic>  $temas
     * @return array<string, array{orden: int, prerrequisitos: list<string>}>
     */
    private function grafo(Collection $temas): array
    {
        return $temas->map(fn (BankTopic $t) => ['orden' => $t->orden, 'prerrequisitos' => $t->prerrequisitos])->all();
    }

    /**
     * @param  list<string>  $orden
     * @param  Collection<string, BankTopic>  $temas
     * @param  array<string, list<string>>  $agregados
     */
    private function pasos(array $orden, Collection $temas, array $agregados): array
    {
        return array_map(fn (string $clave) => [
            ...$this->paraConsola($temas[$clave]),
            'agregado_por' => $agregados[$clave] ?? [],
        ], $orden);
    }

    private function paraConsola(BankTopic $t): array
    {
        return [
            'id' => $t->id,
            'clave' => $t->clave,
            'titulo' => $t->titulo,
            'descripcion' => $t->descripcion,
            'tipo' => $t->tipo,
            'evaluacion' => $t->evaluacion,
            'prerrequisitos' => $t->prerrequisitos,
            'horas' => $t->horas,
            'complejidad' => $t->complejidad,
            'propio' => $t->owner_id !== null,
            'items_count' => $t->items_count ?? 0,
        ];
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'titulo' => ['required', 'string', 'max:160'],
            // Min 10: es lo que exige el contrato de un objetivo, y a él va a dar este texto
            'descripcion' => ['required', 'string', 'min:10', 'max:2000'],
            'tipo' => ['required', Rule::in(['conocimiento', 'habilidad', 'actitud'])],
            'evaluacion' => ['required', 'array', 'min:1'],
            'evaluacion.*' => [Rule::in(['recall', 'comprension', 'practica', 'cis_imms', 'mslq', 'cs'])],
            'prerrequisitos' => ['present', 'array', 'max:30'],
            'prerrequisitos.*' => ['string', 'max:64', 'distinct', Rule::exists('bank_topics', 'clave')
                ->where(fn ($q) => $q->whereNull('owner_id')->orWhere('owner_id', $request->user()->id))],
            'horas' => ['required', 'integer', 'between:1,200'],
            'complejidad' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @param list<string> $prerrequisitos */
    private function sinCiclo(User $user, string $clave, array $prerrequisitos): void
    {
        $grafo = $this->grafo($this->visibles($user));
        $grafo[$clave] = ['orden' => 1000, 'prerrequisitos' => $prerrequisitos];
        if (app(RutaAprendizaje::class)->ciclo($grafo)) {
            throw ValidationException::withMessages(['prerrequisitos' => 'Esos prerrequisitos forman un ciclo: un tema terminaría dependiendo de sí mismo.']);
        }
    }
}
