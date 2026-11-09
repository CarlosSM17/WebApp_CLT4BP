<?php

namespace Tests\Feature\Banco;

use App\Models\Course;
use App\Models\CourseDocument;
use App\Models\DesignElement;
use App\Models\DiffGroup;
use App\Models\DocumentFragment;
use App\Models\Item;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class BancoRecursosTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private array $ejemplo;

    private User $instructor;

    private Course $origen;

    private Course $destino;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ejemplo = json_decode(file_get_contents(base_path('../../packages/contracts/examples/diseno-curso/clase-recorridos.json')), true);
        $this->instructor = $this->instructor();
        $this->origen = $this->curso('Curso anterior');
        $this->destino = $this->curso('Curso nuevo');
        Sanctum::actingAs($this->instructor, ['consola']);
        $this->subir($this->origen, $this->disenoCompleto());
    }

    private function curso(string $titulo, string $lenguaje = 'c', ?User $instructor = null): Course
    {
        $instructor ??= $this->instructor;
        $curso = Course::create(['owner_id' => $instructor->id, 'titulo' => $titulo, 'lenguaje' => $lenguaje, 'nivel_educativo' => 'universidad']);
        $curso->instructores()->attach($instructor->id, ['rol' => 'responsable']);

        return $curso;
    }

    private function disenoCompleto(): array
    {
        $e = $this->ejemplo;
        $cambios = [];
        foreach ([['objetivo', $e['objetivos']], ['clase', $e['clases']], ['tarea', $e['tareas']], ['soporte', $e['soporte']], ['procedimental', $e['procedimental']], ['variante', $e['variantes']]] as [$tipo, $lista]) {
            foreach ($lista as $contenido) {
                $cambios[] = ['tipo' => $tipo, 'contenido' => $contenido];
            }
        }

        return $cambios;
    }

    private function subir(Course $curso, array $elementos): void
    {
        $cambios = array_map(fn ($e) => ['uid' => $e['contenido']['uid'], 'tipo' => $e['tipo'], 'base_version' => 0, 'contenido' => $e['contenido']], $elementos);
        $r = $this->postJson("/api/v1/courses/{$curso->id}/design/sync", ['cambios' => $cambios])->assertOk();
        foreach ($r->json('resultados') as $resultado) {
            $this->assertSame('ok', $resultado['estado'], json_encode($resultado));
        }
    }

    private function elemento(Course $curso, string $uid): DesignElement
    {
        return DesignElement::where('course_id', $curso->id)->where('uid', $uid)->sole();
    }

    private function delDestino(?string $tipo = null): Collection
    {
        return DesignElement::where('course_id', $this->destino->id)->when($tipo, fn ($q) => $q->where('tipo', $tipo))->get();
    }

    public function test_un_estudiante_no_ve_recursos_y_un_instructor_ajeno_no_copia_ni_consulta(): void
    {
        $clase = $this->elemento($this->origen, 'tc1');

        Sanctum::actingAs($this->estudiante(), ['consola']);
        foreach (['recursos', "recursos/{$clase->id}", 'items-cursos', 'documentos'] as $ruta) {
            $this->getJson("/api/v1/banco/{$ruta}")->assertForbidden();
        }

        $ajeno = $this->instructor();
        Sanctum::actingAs($ajeno, ['consola']);
        $this->getJson("/api/v1/banco/recursos/{$clase->id}")->assertForbidden();
        // Ni llevarse material de un curso ajeno a uno propio, ni copiar hacia un curso ajeno
        $propio = $this->curso('Propio', instructor: $ajeno);
        $this->postJson("/api/v1/courses/{$propio->id}/banco/recursos", ['elemento_id' => $clase->id])->assertForbidden();
        $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $clase->id])->assertForbidden();
        $this->assertCount(0, DesignElement::where('course_id', $propio->id)->get());
    }

    public function test_el_listado_trae_solo_mis_cursos_y_no_ofrece_objetivos_ni_variantes(): void
    {
        $otro = $this->instructor();
        Sanctum::actingAs($otro, ['consola']);
        $this->subir($this->curso('Ajeno', instructor: $otro), [['tipo' => 'clase', 'contenido' => [...$this->ejemplo['clases'][0], 'uid' => 'tc-ajena', 'titulo' => 'Clase ajena']]]);
        Sanctum::actingAs($this->instructor, ['consola']);

        $filas = collect($this->getJson("/api/v1/banco/recursos?excluir_curso={$this->destino->id}")->assertOk()->json('data'));

        $this->assertSame(['Curso anterior'], $filas->pluck('curso.titulo')->unique()->values()->all());
        $this->assertNotContains('objetivo', $filas->pluck('tipo'));
        $this->assertNotContains('variante', $filas->pluck('tipo'));
        $this->assertSame(3 + 1 + 1, $filas->firstWhere('tipo', 'clase')['piezas'] + 1, 'tres tareas y un soporte cuelgan de la clase');

        $this->assertCount(1, $this->getJson('/api/v1/banco/recursos?tipo=clase')->json('data'));
        $titulo = $this->ejemplo['tareas'][0]['titulo'];
        $this->assertCount(1, $this->getJson('/api/v1/banco/recursos?q='.urlencode(mb_strtolower($titulo)))->json('data'));
        $this->assertCount(0, $this->getJson('/api/v1/banco/recursos?lenguaje=python')->json('data'));
    }

    public function test_copiar_una_clase_lleva_sus_piezas_con_uid_nuevos_y_referencias_reescritas(): void
    {
        $clase = $this->elemento($this->origen, 'tc1');

        $r = $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $clase->id])->assertCreated();

        $nuevoUid = $r->json('data.uid');
        $this->assertSame(1 + 3 + 1 + 1, $r->json('data.elementos'), 'clase, tres tareas, soporte y ayuda');

        $copia = $this->elemento($this->destino, $nuevoUid);
        $this->assertNotSame('tc1', $nuevoUid);
        $this->assertSame($nuevoUid, $copia->contenido->uid);
        $this->assertSame(1, $copia->contenido->orden);

        $tareas = $this->delDestino('tarea');
        $this->assertCount(3, $tareas);
        $this->assertSame([$nuevoUid], $tareas->pluck('padre_uid')->unique()->values()->all());
        $this->assertSame(0, $tareas->where('uid', 'tc1-t1')->count());

        // Las ayudas siguen colgadas de su tarea copiada, no de la original
        $ayuda = $this->delDestino('procedimental')->sole();
        $this->assertContains($ayuda->padre_uid, $tareas->pluck('uid')->all());
        $this->assertSame(['borrador'], $this->delDestino()->pluck('estado')->unique()->values()->all());

        // El original no se tocó
        $this->assertSame('tc1', $this->elemento($this->origen, 'tc1')->contenido->uid);
    }

    public function test_las_variantes_no_se_copian_porque_son_de_los_grupos_del_otro_curso(): void
    {
        $r = $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $this->elemento($this->origen, 'tc1')->id])->assertCreated();

        $this->assertCount(0, $this->delDestino('variante'));
        $this->assertTrue(collect($r->json('data.avisos'))->contains(fn ($a) => str_contains($a, 'variantes')));
    }

    public function test_los_objetivos_se_ligan_por_descripcion_o_se_quitan_con_aviso(): void
    {
        $clase = $this->elemento($this->origen, 'tc1');

        $r = $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $clase->id])->assertCreated();
        $this->assertSame([], $this->elemento($this->destino, $r->json('data.uid'))->contenido->objetivos);
        $this->assertTrue(collect($r->json('data.avisos'))->contains(fn ($a) => str_contains($a, 'OB-1')));

        // El destino ya tiene ese objetivo, con otro código: la clase queda ligada a él
        $objetivo = $this->ejemplo['objetivos'][0];
        $otro = $this->curso('Con objetivo');
        $this->subir($otro, [['tipo' => 'objetivo', 'contenido' => [...$objetivo, 'uid' => 'ob-x', 'codigo' => 'OB-5', 'orden' => 5]]]);

        $r = $this->postJson("/api/v1/courses/{$otro->id}/banco/recursos", ['elemento_id' => $clase->id])->assertCreated();
        $this->assertSame(['OB-5'], $this->elemento($otro, $r->json('data.uid'))->contenido->objetivos);
    }

    public function test_copiar_una_tarea_exige_una_clase_de_destino_y_se_coloca_al_final(): void
    {
        $tarea = $this->elemento($this->origen, 'tc1-t1');
        $url = "/api/v1/courses/{$this->destino->id}/banco/recursos";

        $this->postJson($url, ['elemento_id' => $tarea->id])->assertJsonValidationErrors('padre_uid');
        $this->postJson($url, ['elemento_id' => $tarea->id, 'padre_uid' => 'tc1'])->assertJsonValidationErrors('padre_uid');

        $this->subir($this->destino, [['tipo' => 'clase', 'contenido' => [...$this->ejemplo['clases'][0], 'uid' => 'mi-clase']]]);
        $this->postJson($url, ['elemento_id' => $tarea->id, 'padre_uid' => 'mi-clase'])->assertCreated();
        $r = $this->postJson($url, ['elemento_id' => $tarea->id, 'padre_uid' => 'mi-clase'])->assertCreated();

        $copia = $this->elemento($this->destino, $r->json('data.uid'));
        $this->assertSame('mi-clase', $copia->contenido->clase_uid);
        $this->assertSame('mi-clase', $copia->padre_uid);
        $this->assertSame(2, $copia->contenido->orden, 'después de la que ya tenía');
    }

    public function test_una_ruta_que_no_existe_en_el_destino_no_oculta_la_tarea(): void
    {
        $this->subir($this->origen, [['tipo' => 'tarea', 'contenido' => [...$this->ejemplo['tareas'][0], 'uid' => 'con-ruta', 'rutas' => ['G1', 'G2']]]]);
        $this->subir($this->destino, [['tipo' => 'clase', 'contenido' => [...$this->ejemplo['clases'][0], 'uid' => 'mi-clase']]]);
        DiffGroup::create(['course_id' => $this->destino->id, 'clave' => 'G2', 'nombre' => 'Ruta B', 'nivel' => 'intermedio', 'orden' => 1]);

        $r = $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $this->elemento($this->origen, 'con-ruta')->id, 'padre_uid' => 'mi-clase'])->assertCreated();

        $this->assertSame(['G2'], $this->elemento($this->destino, $r->json('data.uid'))->contenido->rutas);
    }

    public function test_avisa_si_el_material_es_de_otro_lenguaje(): void
    {
        $python = $this->curso('Python', 'python');

        $r = $this->postJson("/api/v1/courses/{$python->id}/banco/recursos", ['elemento_id' => $this->elemento($this->origen, 'tc1')->id])->assertCreated();

        $this->assertTrue(collect($r->json('data.avisos'))->contains(fn ($a) => str_contains($a, 'está en c')));
    }

    public function test_copia_los_medios_citados_con_su_archivo(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cursos/1/medios/me-1.png', 'imagen');
        $tarea = [...$this->ejemplo['tareas'][0], 'uid' => 'con-medio', 'enunciado_md' => 'Mira ![figura](media:me-1)'];
        $this->subir($this->origen, [
            ['tipo' => 'medio', 'contenido' => ['uid' => 'me-1', 'tipo' => 'imagen', 'titulo' => 'Figura', 'duracion_s' => null, 'segmentos' => [], 'transcripcion' => null]],
            ['tipo' => 'tarea', 'contenido' => $tarea],
        ]);
        MediaAsset::create(['course_id' => $this->origen->id, 'uid' => 'me-1', 'ruta' => 'cursos/1/medios/me-1.png', 'mime' => 'image/png', 'bytes' => 6, 'sha256' => 'x', 'subido_por' => $this->instructor->id]);
        $this->subir($this->destino, [['tipo' => 'clase', 'contenido' => [...$this->ejemplo['clases'][0], 'uid' => 'mi-clase']]]);

        $r = $this->postJson("/api/v1/courses/{$this->destino->id}/banco/recursos", ['elemento_id' => $this->elemento($this->origen, 'con-medio')->id, 'padre_uid' => 'mi-clase'])->assertCreated();

        $medio = $this->delDestino('medio')->sole();
        $this->assertNotSame('me-1', $medio->uid);
        $this->assertStringContainsString("media:{$medio->uid}", $this->elemento($this->destino, $r->json('data.uid'))->contenido->enunciado_md);
        $copia = MediaAsset::where('course_id', $this->destino->id)->sole();
        $this->assertSame($medio->uid, $copia->uid);
        Storage::disk('local')->assertExists($copia->ruta);
        $this->assertNotSame('cursos/1/medios/me-1.png', $copia->ruta);
    }

    public function test_los_items_de_otro_curso_se_copian_como_borrador_y_sin_codigo_de_objetivo(): void
    {
        $item = Item::create([
            'course_id' => $this->origen->id, 'objetivo' => 'OB-1', 'tipo' => 'respuesta_corta', 'nivel' => 'recall', 'estado' => 'aprobado',
            'enunciado' => ['md' => '¿Qué es un ciclo?'], 'clave' => ['aceptadas' => ['repetición']],
        ]);

        $this->getJson("/api/v1/banco/items-cursos?excluir_curso={$this->destino->id}")->assertOk()
            ->assertJsonPath('data.0.id', $item->id)->assertJsonPath('data.0.curso.titulo', 'Curso anterior');
        $this->getJson("/api/v1/banco/items-cursos?excluir_curso={$this->origen->id}")->assertOk()->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/courses/{$this->destino->id}/banco/items-cursos", ['ids' => [$item->id]])->assertCreated();

        $copia = Item::where('course_id', $this->destino->id)->sole();
        $this->assertSame('borrador', $copia->estado);
        $this->assertNull($copia->objetivo);
        $this->assertSame(['repetición'], $copia->clave['aceptadas']);

        Sanctum::actingAs($this->instructor(), ['consola']);
        $propio = $this->curso('Otro', instructor: auth()->user());
        $this->postJson("/api/v1/courses/{$propio->id}/banco/items-cursos", ['ids' => [$item->id]])->assertForbidden();
    }

    public function test_un_documento_procesado_se_reutiliza_con_sus_fragmentos_sin_recalcularlos(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('cursos/1/material/a.pdf', 'pdf');
        $documento = CourseDocument::create([
            'course_id' => $this->origen->id, 'titulo' => 'Apuntes', 'nombre_original' => 'a.pdf', 'ruta' => 'cursos/1/material/a.pdf', 'mime' => 'application/pdf',
            'bytes' => 3, 'sha256' => str_repeat('a', 64), 'estado' => 'listo', 'fragmentos' => 1, 'modelo_embeddings' => 'bge-m3', 'subido_por' => $this->instructor->id,
        ]);
        DocumentFragment::create([
            'course_document_id' => $documento->id, 'course_id' => $this->origen->id, 'orden' => 1, 'texto' => 'Un ciclo repite.',
            'embedding' => json_encode(array_fill(0, 1024, 0.1)),
        ]);
        $url = "/api/v1/courses/{$this->destino->id}/banco/documentos/{$documento->id}";

        $this->getJson("/api/v1/banco/documentos?excluir_curso={$this->destino->id}")->assertOk()->assertJsonPath('data.0.titulo', 'Apuntes');
        $this->postJson($url)->assertCreated()->assertJsonPath('data.estado', 'listo');
        $this->postJson($url)->assertStatus(409);

        $copia = CourseDocument::where('course_id', $this->destino->id)->sole();
        $this->assertNotSame($documento->ruta, $copia->ruta);
        Storage::disk('local')->assertExists($copia->ruta);
        $fragmento = DocumentFragment::where('course_document_id', $copia->id)->sole();
        $this->assertSame('Un ciclo repite.', $fragmento->texto);
        $this->assertSame($this->destino->id, $fragmento->course_id);

        $ajeno = $this->instructor();
        Sanctum::actingAs($ajeno, ['consola']);
        $propio = $this->curso('Otro', instructor: $ajeno);
        $this->postJson("/api/v1/courses/{$propio->id}/banco/documentos/{$documento->id}")->assertForbidden();
    }
}
