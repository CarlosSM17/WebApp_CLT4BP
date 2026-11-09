<?php

namespace App\Domain\Banco;

use InvalidArgumentException;

/**
 * Propuesta de ruta de aprendizaje: a partir de los temas elegidos, agrega los prerrequisitos que faltan y los ordena
 * de lo simple a lo complejo (cada tema después de lo que necesita; entre temas libres, el orden sugerido).
 *
 * Los temas se reciben como [clave => ['orden' => int, 'prerrequisitos' => list<string>]]. Un prerrequisito que no está
 * en el arreglo se ignora: el instructor pudo haber borrado un tema propio del que dependía otro.
 */
final class RutaAprendizaje
{
    /**
     * @param  array<string, array{orden: int, prerrequisitos: list<string>}>  $temas
     * @param  list<string>  $elegidos
     * @return array{orden: list<string>, agregados: array<string, list<string>>} agregados: tema => quiénes lo necesitan
     */
    public function proponer(array $temas, array $elegidos): array
    {
        $incluidos = [];
        $agregados = [];
        $pendientes = array_values(array_unique($elegidos));
        while ($pendientes !== []) {
            $clave = array_pop($pendientes);
            if (isset($incluidos[$clave]) || ! isset($temas[$clave])) {
                continue;
            }
            $incluidos[$clave] = true;
            foreach ($temas[$clave]['prerrequisitos'] as $requisito) {
                if (! isset($temas[$requisito])) {
                    continue;
                }
                if (! in_array($requisito, $elegidos, true)) {
                    $agregados[$requisito][] = $clave;
                }
                $pendientes[] = $requisito;
            }
        }
        foreach ($agregados as &$quienes) {
            sort($quienes);
        }

        return ['orden' => $this->ordenar($temas, array_keys($incluidos)), 'agregados' => $agregados];
    }

    /**
     * Revisa un orden ajustado a mano: no lo impide (el instructor decide), pero avisa lo que deja de cumplirse.
     *
     * @param  array<string, array{orden: int, prerrequisitos: list<string>}>  $temas
     * @param  list<string>  $orden
     * @return list<array{tema: string, requisito: string, problema: 'falta'|'despues'}>
     */
    public function avisos(array $temas, array $orden): array
    {
        $posicion = array_flip($orden);
        $avisos = [];
        foreach ($orden as $clave) {
            foreach ($temas[$clave]['prerrequisitos'] ?? [] as $requisito) {
                if (! isset($temas[$requisito])) {
                    continue;
                }
                if (! isset($posicion[$requisito])) {
                    $avisos[] = ['tema' => $clave, 'requisito' => $requisito, 'problema' => 'falta'];
                } elseif ($posicion[$requisito] > $posicion[$clave]) {
                    $avisos[] = ['tema' => $clave, 'requisito' => $requisito, 'problema' => 'despues'];
                }
            }
        }

        return $avisos;
    }

    /**
     * Un ciclo de prerrequisitos (A necesita B y B necesita A) haría imposible ordenar la ruta.
     *
     * @param  array<string, array{orden: int, prerrequisitos: list<string>}>  $temas
     * @return list<string>|null los temas del ciclo, o null si no hay
     */
    public function ciclo(array $temas): ?array
    {
        $estado = [];
        $visitar = function (string $clave, array $camino) use (&$visitar, &$estado, $temas): ?array {
            if (($estado[$clave] ?? null) === 'listo') {
                return null;
            }
            if (($estado[$clave] ?? null) === 'abierto') {
                return array_slice($camino, (int) array_search($clave, $camino, true));
            }
            $estado[$clave] = 'abierto';
            foreach ($temas[$clave]['prerrequisitos'] as $requisito) {
                if (isset($temas[$requisito]) && ($ciclo = $visitar($requisito, [...$camino, $clave]))) {
                    return $ciclo;
                }
            }
            $estado[$clave] = 'listo';

            return null;
        };

        foreach (array_keys($temas) as $clave) {
            if ($ciclo = $visitar($clave, [])) {
                return $ciclo;
            }
        }

        return null;
    }

    /**
     * Orden topológico estable: de los temas cuyos requisitos ya están, va primero el de menor orden sugerido.
     *
     * @param  array<string, array{orden: int, prerrequisitos: list<string>}>  $temas
     * @param  list<string>  $claves
     * @return list<string>
     */
    private function ordenar(array $temas, array $claves): array
    {
        $faltantes = [];
        foreach ($claves as $clave) {
            $faltantes[$clave] = array_values(array_intersect($temas[$clave]['prerrequisitos'], $claves));
        }

        $orden = [];
        while ($faltantes !== []) {
            $listos = array_keys(array_filter($faltantes, fn (array $f) => $f === []));
            if ($listos === []) {
                throw new InvalidArgumentException('Los prerrequisitos forman un ciclo: '.implode(', ', array_keys($faltantes)));
            }
            usort($listos, fn (string $a, string $b) => [$temas[$a]['orden'], $a] <=> [$temas[$b]['orden'], $b]);
            $siguiente = $listos[0];
            $orden[] = $siguiente;
            unset($faltantes[$siguiente]);
            foreach ($faltantes as $clave => $f) {
                $faltantes[$clave] = array_values(array_diff($f, [$siguiente]));
            }
        }

        return $orden;
    }
}
