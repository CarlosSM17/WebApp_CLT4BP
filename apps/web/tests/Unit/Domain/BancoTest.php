<?php

namespace Tests\Unit\Domain;

use App\Domain\Banco\CopiadorElementos;
use App\Domain\Banco\RutaAprendizaje;
use App\Domain\Banco\SelectorItems;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BancoTest extends TestCase
{
    private const TEMAS = [
        'variables' => ['orden' => 1, 'prerrequisitos' => []],
        'operadores' => ['orden' => 2, 'prerrequisitos' => ['variables']],
        'condicionales' => ['orden' => 3, 'prerrequisitos' => ['operadores']],
        'ciclos' => ['orden' => 4, 'prerrequisitos' => ['condicionales']],
        'funciones' => ['orden' => 5, 'prerrequisitos' => ['ciclos']],
        'arreglos' => ['orden' => 6, 'prerrequisitos' => ['ciclos']],
        'cadenas' => ['orden' => 7, 'prerrequisitos' => ['arreglos']],
    ];

    public function test_la_ruta_agrega_los_prerrequisitos_que_faltan_y_dice_quien_los_necesita(): void
    {
        $r = (new RutaAprendizaje)->proponer(self::TEMAS, ['cadenas']);

        $this->assertSame(['variables', 'operadores', 'condicionales', 'ciclos', 'arreglos', 'cadenas'], $r['orden']);
        $this->assertSame(['cadenas'], $r['agregados']['arreglos']);
        $this->assertSame(['arreglos'], $r['agregados']['ciclos']);
        $this->assertArrayNotHasKey('cadenas', $r['agregados']);
    }

    public function test_un_tema_elegido_no_se_marca_como_agregado_aunque_otro_lo_necesite(): void
    {
        $r = (new RutaAprendizaje)->proponer(self::TEMAS, ['ciclos', 'variables']);

        $this->assertArrayNotHasKey('variables', $r['agregados']);
        $this->assertSame(['variables', 'operadores', 'condicionales', 'ciclos'], $r['orden']);
    }

    public function test_entre_temas_libres_manda_el_orden_sugerido_sin_importar_como_se_eligieron(): void
    {
        $this->assertSame(
            (new RutaAprendizaje)->proponer(self::TEMAS, ['funciones', 'arreglos'])['orden'],
            (new RutaAprendizaje)->proponer(self::TEMAS, ['arreglos', 'funciones'])['orden'],
        );
        $this->assertSame(
            ['variables', 'operadores', 'condicionales', 'ciclos', 'funciones', 'arreglos'],
            (new RutaAprendizaje)->proponer(self::TEMAS, ['arreglos', 'funciones'])['orden'],
        );
    }

    public function test_los_temas_desconocidos_y_los_requisitos_borrados_se_ignoran(): void
    {
        $temas = ['a' => ['orden' => 1, 'prerrequisitos' => ['borrado']]];

        $this->assertSame(['a'], (new RutaAprendizaje)->proponer($temas, ['a', 'inexistente'])['orden']);
    }

    public function test_un_ciclo_se_detecta_y_no_se_puede_ordenar(): void
    {
        $temas = [
            'a' => ['orden' => 1, 'prerrequisitos' => ['b']],
            'b' => ['orden' => 2, 'prerrequisitos' => ['c']],
            'c' => ['orden' => 3, 'prerrequisitos' => ['a']],
            'libre' => ['orden' => 4, 'prerrequisitos' => []],
        ];
        $ruta = new RutaAprendizaje;

        $ciclo = $ruta->ciclo($temas);
        sort($ciclo);
        $this->assertSame(['a', 'b', 'c'], $ciclo);
        $this->assertNull($ruta->ciclo(self::TEMAS));

        $this->expectException(InvalidArgumentException::class);
        $ruta->proponer($temas, ['a']);
    }

    public function test_los_avisos_senalan_el_requisito_quitado_y_el_que_quedo_despues(): void
    {
        $avisos = (new RutaAprendizaje)->avisos(self::TEMAS, ['ciclos', 'condicionales', 'arreglos']);

        $this->assertContains(['tema' => 'ciclos', 'requisito' => 'condicionales', 'problema' => 'despues'], $avisos);
        $this->assertContains(['tema' => 'condicionales', 'requisito' => 'operadores', 'problema' => 'falta'], $avisos);
        $this->assertSame([], (new RutaAprendizaje)->avisos(self::TEMAS, ['variables', 'operadores']));
    }

    public function test_la_seleccion_da_lo_mismo_por_tema_y_alterna_los_tipos(): void
    {
        $candidatos = [
            ['id' => 1, 'tema' => 'a', 'tipo' => 'opcion_multiple'],
            ['id' => 2, 'tema' => 'a', 'tipo' => 'opcion_multiple'],
            ['id' => 3, 'tema' => 'a', 'tipo' => 'parsons'],
            ['id' => 4, 'tema' => 'a', 'tipo' => 'prediccion_salida'],
            ['id' => 5, 'tema' => 'b', 'tipo' => 'opcion_multiple'],
            ['id' => 6, 'tema' => 'b', 'tipo' => 'respuesta_corta'],
            ['id' => 7, 'tema' => 'c', 'tipo' => 'parsons'],
        ];

        $r = (new SelectorItems)->elegir($candidatos, ['a', 'b'], 2);
        $this->assertSame([1, 4, 5, 6], $r['ids']); // un tipo distinto antes de repetir
        $this->assertSame([], $r['faltan']);

        // Sin suficientes de otro tipo, repite tipo en la vuelta siguiente en vez de quedarse corto
        $this->assertSame([1, 4, 3, 2], (new SelectorItems)->elegir($candidatos, ['a'], 4)['ids']);
    }

    public function test_la_seleccion_no_repite_lo_ya_usado_y_avisa_lo_que_falta(): void
    {
        $candidatos = [
            ['id' => 1, 'tema' => 'a', 'tipo' => 'opcion_multiple'],
            ['id' => 2, 'tema' => 'a', 'tipo' => 'parsons'],
        ];

        $r = (new SelectorItems)->elegir($candidatos, ['a', 'vacio'], 2, usados: [1]);

        $this->assertSame([2], $r['ids']);
        $this->assertSame(['a' => 1, 'vacio' => 2], $r['faltan']);
    }

    public function test_la_copia_cambia_los_uid_y_reescribe_todas_las_referencias(): void
    {
        $elementos = [
            ['uid' => 'tc-1', 'tipo' => 'clase', 'contenido' => (object) ['uid' => 'tc-1', 'titulo' => 'Tema de tc-10']],
            ['uid' => 'tc-1-t_a', 'tipo' => 'tarea', 'contenido' => (object) [
                'uid' => 'tc-1-t_a', 'clase_uid' => 'tc-1', 'enunciado_md' => 'Mira ![video](media:me_1) y sigue',
                'arcs' => (object) [], 'rutas' => [],
            ]],
            ['uid' => 'me_1', 'tipo' => 'medio', 'contenido' => (object) ['uid' => 'me_1', 'titulo' => 'me_1']],
        ];
        $n = 0;
        $azar = function (string $prefijo) use (&$n) {
            return $prefijo.'_nuevo'.++$n;
        };

        $r = (new CopiadorElementos)->copiar($elementos, $azar);
        [$clase, $tarea, $medio] = $r['elementos'];

        $this->assertSame('tc_nuevo1', $clase['uid']);
        $this->assertSame('tc_nuevo1', $clase['contenido']->uid);
        $this->assertSame('Tema de tc-10', $clase['contenido']->titulo, 'tc-10 no es tc-1');
        $this->assertSame('ta_nuevo2', $tarea['contenido']->uid);
        $this->assertSame('tc_nuevo1', $tarea['contenido']->clase_uid);
        $this->assertSame('Mira ![video](media:me_nuevo3) y sigue', $tarea['contenido']->enunciado_md);
        $this->assertSame('me_nuevo3', $medio['contenido']->uid);
        $this->assertSame('me_nuevo3', $r['uids']['me_1']);
        $this->assertSame('{}', json_encode($tarea['contenido']->arcs), 'un objeto vacío sigue siendo {}');
        $this->assertSame('[]', json_encode($tarea['contenido']->rutas));
    }
}
