<?php

namespace Tests\Feature\Banco;

use App\Models\BankTopic;
use App\Models\Course;
use App\Models\DesignElement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class BancoRutaTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private function cursoCon(): array
    {
        $instructor = $this->instructor();
        $curso = Course::create(['owner_id' => $instructor->id, 'titulo' => 'Curso', 'lenguaje' => 'c', 'nivel_educativo' => 'universidad']);
        $curso->instructores()->attach($instructor->id, ['rol' => 'responsable']);

        return [$curso, $instructor];
    }

    private function tema(string $clave, array $prerrequisitos = [], int $orden = 10, array $extra = []): BankTopic
    {
        return BankTopic::create([
            'clave' => $clave, 'titulo' => "Tema {$clave}", 'descripcion' => "Resolver problemas de {$clave} correctamente.",
            'tipo' => 'habilidad', 'evaluacion' => ['recall', 'practica'], 'prerrequisitos' => $prerrequisitos, 'lenguajes' => [],
            'orden' => $orden, 'horas' => 4, 'complejidad' => "Más complejo por {$clave}.", ...$extra,
        ]);
    }

    private function datosTema(array $extra = []): array
    {
        return [
            'titulo' => 'Mi tema', 'descripcion' => 'Aplicar mi tema en un problema real.', 'tipo' => 'habilidad',
            'evaluacion' => ['practica'], 'prerrequisitos' => [], 'horas' => 3, ...$extra,
        ];
    }

    public function test_un_estudiante_no_entra_a_ningun_lado_y_un_instructor_ajeno_no_aplica_la_ruta(): void
    {
        [$curso] = $this->cursoCon();
        $this->tema('a');

        Sanctum::actingAs($this->estudiante(), ['consola']);
        $this->getJson('/api/v1/banco/temas')->assertForbidden();
        $this->postJson('/api/v1/banco/temas', $this->datosTema())->assertForbidden();
        $this->postJson('/api/v1/banco/ruta', ['temas' => ['a']])->assertForbidden();
        $this->postJson('/api/v1/banco/ruta/avisos', ['orden' => ['a']])->assertForbidden();

        Sanctum::actingAs($this->instructor(), ['consola']);
        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['a'], 'crear_clases' => true])->assertForbidden();
    }

    public function test_el_catalogo_no_se_edita_y_un_tema_propio_solo_lo_toca_su_dueno(): void
    {
        [, $dueno] = $this->cursoCon();
        $catalogo = $this->tema('catalogo');
        $propio = $this->tema('propio', extra: ['owner_id' => $dueno->id]);

        Sanctum::actingAs($dueno, ['consola']);
        $this->putJson("/api/v1/banco/temas/{$catalogo->id}", $this->datosTema())->assertForbidden();
        $this->deleteJson("/api/v1/banco/temas/{$catalogo->id}")->assertForbidden();

        Sanctum::actingAs($this->instructor(), ['consola']);
        $this->putJson("/api/v1/banco/temas/{$propio->id}", $this->datosTema())->assertForbidden();
        $this->deleteJson("/api/v1/banco/temas/{$propio->id}")->assertForbidden();
        $this->assertSame([], array_values(array_filter(
            $this->getJson('/api/v1/banco/temas')->assertOk()->json('data.*.clave'),
            fn ($c) => $c === 'propio',
        )), 'los temas de otro instructor no se ven');
    }

    public function test_un_instructor_agrega_edita_y_borra_sus_propios_objetivos(): void
    {
        [, $instructor] = $this->cursoCon();
        $this->tema('base');
        Sanctum::actingAs($instructor, ['consola']);

        $creado = $this->postJson('/api/v1/banco/temas', $this->datosTema(['prerrequisitos' => ['base']]))->assertCreated()
            ->assertJsonPath('data.propio', true)->json('data');
        $this->assertStringStartsWith('propio-', $creado['clave']);

        $this->putJson("/api/v1/banco/temas/{$creado['id']}", $this->datosTema(['titulo' => 'Renombrado']))->assertOk()
            ->assertJsonPath('data.titulo', 'Renombrado');
        $this->deleteJson("/api/v1/banco/temas/{$creado['id']}")->assertNoContent();
        $this->assertNull(BankTopic::find($creado['id']));
    }

    public function test_un_objetivo_propio_necesita_descripcion_y_no_puede_depender_de_lo_que_no_ve(): void
    {
        [, $instructor] = $this->cursoCon();
        $ajeno = $this->tema('ajeno', extra: ['owner_id' => $this->instructor()->id]);
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson('/api/v1/banco/temas', $this->datosTema(['descripcion' => 'corto']))->assertJsonValidationErrors('descripcion');
        $this->postJson('/api/v1/banco/temas', $this->datosTema(['prerrequisitos' => [$ajeno->clave]]))->assertJsonValidationErrors('prerrequisitos.0');
    }

    public function test_no_se_guardan_prerrequisitos_que_formen_un_ciclo(): void
    {
        [, $instructor] = $this->cursoCon();
        $a = $this->tema('a', extra: ['owner_id' => $instructor->id]);
        $this->tema('b', ['a'], extra: ['owner_id' => $instructor->id]);
        Sanctum::actingAs($instructor, ['consola']);

        $this->putJson("/api/v1/banco/temas/{$a->id}", $this->datosTema(['prerrequisitos' => ['b']]))->assertJsonValidationErrors('prerrequisitos');
    }

    public function test_la_ruta_propuesta_agrega_requisitos_y_avisa_del_orden_ajustado_a_mano(): void
    {
        [, $instructor] = $this->cursoCon();
        $this->tema('variables', orden: 10);
        $this->tema('ciclos', ['variables'], 20);
        $this->tema('arreglos', ['ciclos'], 30);
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson('/api/v1/banco/ruta', ['temas' => ['arreglos']])->assertOk()
            ->assertJsonPath('data.ruta.0.clave', 'variables')
            ->assertJsonPath('data.ruta.0.agregado_por', ['ciclos'])
            ->assertJsonPath('data.ruta.1.clave', 'ciclos')
            ->assertJsonPath('data.ruta.2.clave', 'arreglos')
            ->assertJsonPath('data.ruta.2.agregado_por', []);

        $this->postJson('/api/v1/banco/ruta/avisos', ['orden' => ['arreglos', 'ciclos']])->assertOk()
            ->assertJsonFragment(['tema' => 'arreglos', 'requisito' => 'ciclos', 'problema' => 'despues'])
            ->assertJsonFragment(['tema' => 'ciclos', 'requisito' => 'variables', 'problema' => 'falta']);
    }

    public function test_el_listado_oculta_los_temas_de_otro_lenguaje(): void
    {
        [, $instructor] = $this->cursoCon();
        $this->tema('general');
        $this->tema('apuntadores', extra: ['lenguajes' => ['c', 'cpp']]);
        Sanctum::actingAs($instructor, ['consola']);

        $this->assertSame(['general'], $this->getJson('/api/v1/banco/temas?lenguaje=python')->assertOk()->json('data.*.clave'));
        $this->assertCount(2, $this->getJson('/api/v1/banco/temas?lenguaje=c')->json('data'));
    }

    public function test_aplicar_crea_objetivos_y_clases_en_orden_validos_y_como_borrador(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $this->tema('variables');
        $this->tema('ciclos');
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['ciclos', 'variables'], 'crear_clases' => true])
            ->assertCreated()->assertJsonPath('data', ['objetivos' => 2, 'clases' => 2, 'reutilizados' => 0]);

        $diseno = collect($this->getJson("/api/v1/courses/{$curso->id}/design")->assertOk()->json('cambios'));
        $objetivos = $diseno->where('tipo', 'objetivo')->sortBy('orden')->values();
        $clases = $diseno->where('tipo', 'clase')->sortBy('orden')->values();

        $this->assertSame(['OB-1', 'OB-2'], $objetivos->pluck('contenido.codigo')->all());
        $this->assertSame('Resolver problemas de ciclos correctamente.', $objetivos[0]['contenido']['descripcion']);
        $this->assertSame(['Tema ciclos', 'Tema variables'], $clases->pluck('contenido.titulo')->all());
        $this->assertSame(['OB-2'], $clases[1]['contenido']['objetivos']);
        $this->assertSame(['borrador'], $diseno->pluck('estado')->unique()->values()->all());
    }

    public function test_aplicar_respeta_lo_que_el_curso_ya_tiene_y_no_duplica(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $this->tema('variables');
        $this->tema('ciclos');
        Sanctum::actingAs($instructor, ['consola']);
        $this->postJson("/api/v1/courses/{$curso->id}/design/sync", ['cambios' => [[
            'uid' => 'ob-mio', 'tipo' => 'objetivo', 'base_version' => 0, 'contenido' => [
                'uid' => 'ob-mio', 'codigo' => 'OB-7', 'orden' => 7, 'descripcion' => 'Un objetivo escrito a mano antes.',
                'tipo' => 'habilidad', 'evaluacion' => ['recall'],
            ],
        ]]])->assertOk();

        $ruta = ['orden' => ['variables'], 'crear_clases' => false];
        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", $ruta)->assertCreated()->assertJsonPath('data.objetivos', 1);

        // Continúa la numeración del curso: no pisa OB-7
        $nuevo = DesignElement::where('course_id', $curso->id)->where('tipo', 'objetivo')->where('uid', '!=', 'ob-mio')->sole();
        $this->assertSame('OB-8', $nuevo->contenido->codigo);
        $this->assertSame(0, DesignElement::where('course_id', $curso->id)->where('tipo', 'clase')->count());

        // Repetir no duplica el objetivo; y las clases que faltaban sí se agregan, ligadas al código existente
        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['variables'], 'crear_clases' => true])
            ->assertCreated()->assertJsonPath('data', ['objetivos' => 0, 'clases' => 1, 'reutilizados' => 1]);
        $clase = DesignElement::where('course_id', $curso->id)->where('tipo', 'clase')->sole();
        $this->assertSame(['OB-8'], $clase->contenido->objetivos);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['variables'], 'crear_clases' => true])
            ->assertCreated()->assertJsonPath('data', ['objetivos' => 0, 'clases' => 0, 'reutilizados' => 1]);
    }

    public function test_no_se_puede_aplicar_un_tema_ajeno_ni_inexistente(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $this->tema('ajeno', extra: ['owner_id' => $this->instructor()->id]);
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['ajeno'], 'crear_clases' => false])->assertJsonValidationErrors('orden');
        $this->postJson("/api/v1/courses/{$curso->id}/banco/ruta", ['orden' => ['nada'], 'crear_clases' => false])->assertJsonValidationErrors('orden');
    }
}
