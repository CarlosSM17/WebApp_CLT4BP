<?php

namespace App\Domain\Banco;

/**
 * Selección equilibrada para una prueba: la misma cantidad de ítems por tema, alternando tipos para que no sea solo
 * opción múltiple. Es determinista (el instructor ve y ajusta la selección antes de crear la prueba) y no repite lo
 * que el curso ya usó en otra prueba, de modo que la forma B no sale con las preguntas de la forma A.
 */
final class SelectorItems
{
    private const ORDEN_TIPOS = ['opcion_multiple', 'prediccion_salida', 'parsons', 'respuesta_corta', 'programacion'];

    /**
     * @param  list<array{id: int, tema: string, tipo: string}>  $candidatos
     * @param  list<string>  $temas
     * @param  list<int>  $usados
     * @return array{ids: list<int>, faltan: array<string, int>} faltan: tema => cuántos no se pudieron cubrir
     */
    public function elegir(array $candidatos, array $temas, int $porTema, array $usados = []): array
    {
        $ids = [];
        $faltan = [];
        foreach ($temas as $tema) {
            $porTipo = [];
            foreach ($candidatos as $c) {
                if ($c['tema'] === $tema && ! in_array($c['id'], $usados, true)) {
                    $porTipo[$c['tipo']][] = $c['id'];
                }
            }
            foreach ($porTipo as &$lista) {
                sort($lista);
            }
            unset($lista);

            $elegidos = $this->alternar($porTipo, $porTema);
            array_push($ids, ...$elegidos);
            if (count($elegidos) < $porTema) {
                $faltan[$tema] = $porTema - count($elegidos);
            }
        }

        return ['ids' => $ids, 'faltan' => $faltan];
    }

    /**
     * Toma uno de cada tipo por vuelta, hasta completar.
     *
     * @param  array<string, list<int>>  $porTipo
     * @return list<int>
     */
    private function alternar(array $porTipo, int $cuantos): array
    {
        $elegidos = [];
        while (count($elegidos) < $cuantos && $porTipo !== []) {
            foreach (self::ORDEN_TIPOS as $tipo) {
                if (count($elegidos) === $cuantos) {
                    break;
                }
                if (isset($porTipo[$tipo])) {
                    $elegidos[] = array_shift($porTipo[$tipo]);
                    if ($porTipo[$tipo] === []) {
                        unset($porTipo[$tipo]);
                    }
                }
            }
        }

        return $elegidos;
    }
}
