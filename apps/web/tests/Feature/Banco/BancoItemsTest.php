<?php

namespace Tests\Feature\Banco;

use App\Domain\Pruebas\CalificadorItems;
use App\Models\Assessment;
use App\Models\BankItem;
use App\Models\BankTopic;
use App\Models\Course;
use App\Models\Item;
use App\Services\Banco\VerificadorBanco;
use App\Services\Codigo\EjecutorCodigo;
use App\Services\Codigo\ResultadoEjecucion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreaUsuarios;
use Tests\TestCase;

class BancoItemsTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    private string $salidaFalsa = "5\n";

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutor falso: siempre imprime lo que indique la prueba
        $this->app->bind(EjecutorCodigo::class, fn () => new class($this) implements EjecutorCodigo
        {
            public function __construct(private readonly BancoItemsTest $prueba) {}

            public function ejecutar(string $lenguaje, string $codigo, string $entrada = '', int $limiteMs = 3000): ResultadoEjecucion
            {
                return new ResultadoEjecucion(true, $this->prueba->salida(), '', 0, false);
            }
        });
    }

    public function salida(): string
    {
        return $this->salidaFalsa;
    }

    private function cursoCon(string $lenguaje = 'c'): array
    {
        $instructor = $this->instructor();
        $curso = Course::create(['owner_id' => $instructor->id, 'titulo' => 'Curso', 'lenguaje' => $lenguaje, 'nivel_educativo' => 'universidad']);
        $curso->instructores()->attach($instructor->id, ['rol' => 'responsable']);

        return [$curso, $instructor];
    }

    private function tema(string $clave = 'ciclos'): BankTopic
    {
        return BankTopic::create([
            'clave' => $clave, 'titulo' => ucfirst($clave), 'descripcion' => "Objetivo del tema {$clave} con detalle.",
            'tipo' => 'habilidad', 'evaluacion' => ['recall'], 'prerrequisitos' => [], 'lenguajes' => [],
        ]);
    }

    private function om(BankTopic $tema, array $lenguajes = [], string $sufijo = '1', array $extra = []): BankItem
    {
        return BankItem::create([
            'codigo' => "{$tema->clave}-om-{$sufijo}", 'bank_topic_id' => $tema->id, 'tipo' => 'opcion_multiple', 'nivel' => 'recall', 'lenguajes' => $lenguajes,
            'enunciado' => ['md' => '¿Pregunta?', 'opciones' => [['id' => 'a', 'texto' => 'Sí'], ['id' => 'b', 'texto' => 'No']]],
            'clave' => ['correcta' => 'a'],
            ...$extra,
        ]);
    }

    private function problema(BankTopic $tema, array $lenguajes = ['c', 'cpp']): BankItem
    {
        return BankItem::create([
            'codigo' => "{$tema->clave}-prog", 'bank_topic_id' => $tema->id, 'tipo' => 'programacion', 'nivel' => 'practica', 'lenguajes' => $lenguajes,
            'enunciado' => ['md' => 'Suma'], 'solucion' => 'int main(){}',
            'casos_prueba' => [['entrada' => '2 3', 'salida_esperada' => '5', 'oculto' => false]],
        ]);
    }

    public function test_un_estudiante_no_ve_el_banco_y_un_instructor_ajeno_no_toca_el_curso(): void
    {
        [$curso] = $this->cursoCon();
        $item = $this->om($this->tema());
        $datosPrueba = ['nombre' => 'P', 'momento' => 'pre', 'tipo' => 'teorica', 'forma' => 'A', 'items' => [['id' => $item->id, 'puntos' => 1]]];

        Sanctum::actingAs($this->estudiante(), ['consola']);
        $this->getJson('/api/v1/banco/items')->assertForbidden();

        Sanctum::actingAs($this->instructor(), ['consola']);
        $this->getJson("/api/v1/banco/items?curso={$curso->id}")->assertForbidden();
        $this->postJson("/api/v1/courses/{$curso->id}/banco/items", ['ids' => [$item->id]])->assertForbidden();
        $this->postJson("/api/v1/courses/{$curso->id}/banco/seleccion", ['tipo' => 'teorica', 'temas' => ['ciclos'], 'por_tema' => 1])->assertForbidden();
        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", $datosPrueba)->assertForbidden();
    }

    public function test_el_listado_filtra_por_lenguaje_muestra_la_clave_y_no_la_verificacion(): void
    {
        $tema = $this->tema();
        $this->om($tema, [], 'neutro', ['verificacion' => ['programa' => 'secreto', 'salida' => '1']]);
        $this->om($tema, ['c', 'cpp'], 'c');
        $this->om($tema, ['python'], 'py');
        Sanctum::actingAs($this->instructor(), ['consola']);

        $codigos = $this->getJson('/api/v1/banco/items?lenguaje=c')->assertOk()->json('data.*.codigo');
        $this->assertSame(['ciclos-om-neutro', 'ciclos-om-c'], $codigos);

        $this->getJson('/api/v1/banco/items?lenguaje=python')->assertOk()
            ->assertJsonPath('data.1.codigo', 'ciclos-om-py')
            ->assertJsonPath('data.0.clave.correcta', 'a')
            ->assertJsonPath('data.0.tema', 'ciclos')
            ->assertJsonMissingPath('data.0.verificacion');
    }

    public function test_agregar_copia_como_borrador_sin_duplicar_y_el_original_no_cambia(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $item = $this->om($this->tema());
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/items", ['ids' => [$item->id]])
            ->assertOk()->assertJsonPath('data.nuevos', 1)->assertJsonPath('data.ya_estaban', 0);
        $this->postJson("/api/v1/courses/{$curso->id}/banco/items", ['ids' => [$item->id]])
            ->assertOk()->assertJsonPath('data.nuevos', 0)->assertJsonPath('data.ya_estaban', 1);

        $copia = Item::where('course_id', $curso->id)->sole();
        $this->assertSame('borrador', $copia->estado);
        $this->assertSame($item->id, $copia->bank_item_id);

        $copia->update(['enunciado' => ['md' => 'Editada', 'opciones' => $item->enunciado['opciones']]]);
        $this->assertSame('¿Pregunta?', $item->fresh()->enunciado['md']);

        $this->getJson("/api/v1/banco/items?curso={$curso->id}")->assertJsonPath('data.0.en_curso', true);
    }

    public function test_un_item_de_otro_lenguaje_no_entra_al_curso(): void
    {
        [$curso, $instructor] = $this->cursoCon('python');
        $item = $this->om($this->tema(), ['c', 'cpp']);
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/items", ['ids' => [$item->id]])->assertStatus(422);
        $this->assertSame(0, Item::count());
    }

    public function test_la_seleccion_propone_por_tema_sin_crear_nada_y_no_repite_lo_ya_usado(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $tema = $this->tema();
        $a = $this->om($tema, [], '1');
        $b = $this->om($tema, [], '2');
        $this->problema($tema);
        Sanctum::actingAs($instructor, ['consola']);
        $pedir = ['tipo' => 'teorica', 'temas' => ['ciclos'], 'por_tema' => 1];

        $this->postJson("/api/v1/courses/{$curso->id}/banco/seleccion", $pedir)->assertOk()
            ->assertJsonPath('data.ids', [$a->id])->assertJsonPath('data.faltan', []);
        $this->assertSame(0, Item::count());

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'Forma A', 'momento' => 'pre', 'tipo' => 'teorica', 'forma' => 'A', 'items' => [['id' => $a->id, 'puntos' => 1]],
        ])->assertCreated();

        // La forma B ya no lleva la pregunta de la forma A
        $this->postJson("/api/v1/courses/{$curso->id}/banco/seleccion", $pedir)->assertOk()->assertJsonPath('data.ids', [$b->id]);
        $this->postJson("/api/v1/courses/{$curso->id}/banco/seleccion", [...$pedir, 'por_tema' => 3])->assertOk()
            ->assertJsonPath('data.faltan.ciclos', 2);
    }

    public function test_crear_la_prueba_aprueba_las_copias_y_verifica_los_problemas(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $problema = $this->problema($this->tema());
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'Diagnóstico práctico', 'momento' => 'pre', 'tipo' => 'practica', 'forma' => 'A', 'tiempo_limite_min' => 40,
            'items' => [['id' => $problema->id, 'puntos' => 10]],
        ])->assertCreated()->assertJsonPath('data.items_count', 1);

        $prueba = Assessment::where('course_id', $curso->id)->sole();
        $copia = $prueba->items()->sole();
        $this->assertSame('pre', $prueba->momento);
        $this->assertSame('aprobado', $copia->estado);
        $this->assertNotNull($copia->verificado_at);
        $this->assertSame('c', $copia->lenguaje);
        $this->assertEquals(10, $copia->pivot->puntos);
    }

    public function test_una_solucion_que_falla_no_se_aprueba_y_no_se_crea_la_prueba(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $problema = $this->problema($this->tema());
        $this->salidaFalsa = "999\n";
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'P', 'momento' => 'pre', 'tipo' => 'practica', 'forma' => 'A', 'items' => [['id' => $problema->id, 'puntos' => 10]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');

        $this->assertSame(0, Assessment::count());
        $this->assertSame('borrador', Item::sole()->estado);
    }

    public function test_lo_ya_verificado_no_se_repite_si_otra_solucion_falla_y_se_reintenta(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $bueno = $this->problema($this->tema('a'));
        $malo = BankItem::create([
            'codigo' => 'malo', 'bank_topic_id' => $this->tema('b')->id, 'tipo' => 'programacion', 'nivel' => 'practica', 'lenguajes' => ['c'],
            'enunciado' => ['md' => 'Espera otra salida'], 'solucion' => 'int main(){}',
            'casos_prueba' => [['entrada' => '', 'salida_esperada' => 'otra', 'oculto' => false]],
        ]);
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'P', 'momento' => 'pre', 'tipo' => 'practica', 'forma' => 'A',
            'items' => [['id' => $bueno->id, 'puntos' => 10], ['id' => $malo->id, 'puntos' => 10]],
        ])->assertStatus(422);

        $this->assertNotNull(Item::where('bank_item_id', $bueno->id)->sole()->verificado_at);
        $this->assertNull(Item::where('bank_item_id', $malo->id)->sole()->verificado_at);
        $this->assertSame(0, Assessment::count());
    }

    public function test_una_prueba_no_admite_mas_problemas_de_los_que_se_pueden_verificar_al_momento(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $tema = $this->tema();
        $ids = [];
        foreach (range(1, 21) as $n) {
            $ids[] = ['id' => BankItem::create([
                'codigo' => "p{$n}", 'bank_topic_id' => $tema->id, 'tipo' => 'programacion', 'nivel' => 'practica', 'lenguajes' => ['c'],
                'enunciado' => ['md' => 'Suma'], 'solucion' => 'x', 'casos_prueba' => [['entrada' => '', 'salida_esperada' => '5', 'oculto' => false]],
            ])->id, 'puntos' => 1];
        }
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'P', 'momento' => 'pre', 'tipo' => 'practica', 'forma' => 'A', 'items' => $ids,
        ])->assertStatus(422)->assertJsonValidationErrors('items');
        $this->assertSame(0, Item::count());
    }

    public function test_el_tipo_de_prueba_debe_corresponder_al_nivel_de_los_items(): void
    {
        [$curso, $instructor] = $this->cursoCon();
        $tema = $this->tema();
        Sanctum::actingAs($instructor, ['consola']);

        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'P', 'momento' => 'pre', 'tipo' => 'teorica', 'forma' => 'A', 'items' => [['id' => $this->problema($tema)->id, 'puntos' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items');
        $this->postJson("/api/v1/courses/{$curso->id}/banco/prueba", [
            'nombre' => 'P', 'momento' => 'pre', 'tipo' => 'practica', 'forma' => 'A', 'items' => [['id' => $this->om($tema)->id, 'puntos' => 1]],
        ])->assertStatus(422);
        $this->assertSame(0, Assessment::count());
    }

    public function test_cargar_el_catalogo_real_es_repetible_y_respeta_lo_de_los_instructores(): void
    {
        $this->artisan('banco:cargar')->assertSuccessful();
        $temas = BankTopic::count();
        $items = BankItem::count();
        $this->assertGreaterThanOrEqual(18, $temas);
        $this->assertGreaterThan(150, $items);

        [$curso, $instructor] = $this->cursoCon();
        $propio = BankTopic::create([
            'clave' => 'propio-x', 'owner_id' => $instructor->id, 'titulo' => 'Mío', 'descripcion' => 'Un objetivo propio de prueba.',
            'tipo' => 'habilidad', 'evaluacion' => ['recall'], 'prerrequisitos' => [], 'lenguajes' => [],
        ]);
        $sobrante = $this->om(BankTopic::where('clave', 'ciclos')->sole(), [], 'ya-no-existe');
        $copia = Item::create(['course_id' => $curso->id, 'bank_item_id' => $sobrante->id, 'tipo' => 'opcion_multiple', 'nivel' => 'recall', 'enunciado' => $sobrante->enunciado, 'clave' => $sobrante->clave]);

        $this->artisan('banco:cargar')->assertSuccessful();

        $this->assertSame($temas + 1, BankTopic::count());
        $this->assertSame($items, BankItem::count(), 'lo que salió del catálogo se quita');
        $this->assertModelExists($propio);
        $this->assertNull($copia->fresh()->bank_item_id, 'la copia del curso sobrevive');
    }

    public function test_todo_tema_del_catalogo_se_puede_evaluar_en_sus_lenguajes(): void
    {
        $this->artisan('banco:cargar')->assertSuccessful();

        foreach (BankTopic::with('items')->get() as $tema) {
            foreach (['c', 'cpp', 'python'] as $lenguaje) {
                if (! $tema->sirveEn($lenguaje)) {
                    continue;
                }
                $items = $tema->items->filter(fn (BankItem $i) => $i->sirveEn($lenguaje));
                $this->assertGreaterThanOrEqual(4, $items->where('nivel', '!=', 'practica')->count(), "{$tema->clave} en {$lenguaje}: teóricos");
                $this->assertGreaterThanOrEqual(1, $items->where('nivel', 'practica')->count(), "{$tema->clave} en {$lenguaje}: prácticos");
            }
        }
    }

    public function test_cada_respuesta_del_catalogo_obtiene_la_nota_completa_y_el_estudiante_nunca_ve_la_clave(): void
    {
        $this->artisan('banco:cargar')->assertSuccessful();
        $calificador = new CalificadorItems;
        $curso = $this->cursoCon()[0];

        foreach (BankItem::where('tipo', '!=', 'programacion')->get() as $b) {
            $correcta = match ($b->tipo) {
                'opcion_multiple' => $b->clave['correcta'],
                'respuesta_corta' => $b->clave['aceptadas'][0],
                'prediccion_salida' => $b->clave['salida'],
                'parsons' => $b->clave['orden'],
            };
            $this->assertSame(1.0, $calificador->fraccion($b->tipo, $b->clave, $correcta), $b->codigo);

            $vista = json_encode((new Item([
                'course_id' => $curso->id, 'tipo' => $b->tipo, 'nivel' => $b->nivel, 'enunciado' => $b->enunciado, 'clave' => $b->clave,
            ]))->paraEstudiante(), JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString('"clave"', $vista, $b->codigo);
            $this->assertStringNotContainsString('"correcta"', $vista, $b->codigo);
            $this->assertStringNotContainsString('"orden"', $vista, $b->codigo);
        }

        foreach (BankItem::where('tipo', 'programacion')->get() as $b) {
            $this->assertNotEmpty($b->solucion, $b->codigo);
            $visible = (new Item([
                'course_id' => $curso->id, 'tipo' => $b->tipo, 'nivel' => $b->nivel, 'enunciado' => $b->enunciado, 'casos_prueba' => $b->casos_prueba, 'solucion' => $b->solucion,
            ]))->paraEstudiante();
            $this->assertNotEmpty($visible['ejemplos'], "{$b->codigo}: necesita un ejemplo visible");
            $this->assertStringNotContainsString('solucion', json_encode($visible), $b->codigo);
        }
    }

    public function test_el_verificador_detecta_una_clave_que_no_coincide_con_el_programa(): void
    {
        $tema = $this->tema();
        $item = BankItem::create([
            'codigo' => 'x', 'bank_topic_id' => $tema->id, 'tipo' => 'prediccion_salida', 'nivel' => 'comprension', 'lenguajes' => ['c'],
            'enunciado' => ['md' => '¿Qué imprime?'], 'clave' => ['salida' => "5\n"],
            'verificacion' => ['programa' => 'int main(){}', 'entrada' => '', 'salida' => "5\n"],
        ]);
        $verificador = app(VerificadorBanco::class);

        $this->assertNull($verificador->problema($item, 'c'));

        $this->salidaFalsa = "6\n";
        $this->assertStringContainsString('la clave dice', (string) $verificador->problema($item, 'c'));
        $this->assertStringContainsString('pasa 0 de 1', (string) $verificador->problema($this->problema($tema), 'c'));
    }
}
