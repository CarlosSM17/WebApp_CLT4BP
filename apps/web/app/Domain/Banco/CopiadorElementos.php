<?php

namespace App\Domain\Banco;

/**
 * Prepara elementos del diseño para reutilizarlos en otro curso: cada uno recibe un uid nuevo y las referencias entre
 * ellos (clase_uid, tarea_uid, ![…](media:uid) dentro de un Markdown) se reescriben.
 *
 * El contenido llega como objeto, igual que al sincronizar, para que un {} vacío no se convierta en [].
 */
final class CopiadorElementos
{
    private const PREFIJOS = [
        'objetivo' => 'ob', 'clase' => 'tc', 'tarea' => 'ta', 'soporte' => 'so',
        'procedimental' => 'pr', 'practica_parcial' => 'pp', 'medio' => 'me',
    ];

    /**
     * @param  list<array{uid: string, tipo: string, contenido: object}>  $elementos
     * @param  callable(string): string  $azar  devuelve un sufijo aleatorio para el prefijo dado
     * @return array{elementos: list<array{uid: string, tipo: string, contenido: object}>, uids: array<string, string>}
     */
    public function copiar(array $elementos, callable $azar): array
    {
        $uids = [];
        foreach ($elementos as $e) {
            $uids[$e['uid']] = $azar(self::PREFIJOS[$e['tipo']] ?? 'el');
        }

        $copias = [];
        foreach ($elementos as $e) {
            $copias[] = [
                'uid' => $uids[$e['uid']],
                'tipo' => $e['tipo'],
                'contenido' => $this->reescribir($e['contenido'], $uids),
            ];
        }

        return ['elementos' => $copias, 'uids' => $uids];
    }

    /** @param array<string, string> $uids */
    private function reescribir(mixed $valor, array $uids): mixed
    {
        if (is_string($valor)) {
            return $this->reemplazar($valor, $uids);
        }
        if (is_array($valor)) {
            return array_map(fn ($v) => $this->reescribir($v, $uids), $valor);
        }
        if (is_object($valor)) {
            $copia = new \stdClass;
            foreach (get_object_vars($valor) as $campo => $v) {
                $copia->{$campo} = $this->reescribir($v, $uids);
            }

            return $copia;
        }

        return $valor;
    }

    /**
     * Solo reemplaza el uid completo: `tc-1` no debe tocar a `tc-10` ni a `tc-1-t_a`.
     *
     * @param  array<string, string>  $uids
     */
    private function reemplazar(string $texto, array $uids): string
    {
        if ($uids === []) {
            return $texto;
        }
        $claves = array_keys($uids);
        usort($claves, fn (string $a, string $b) => strlen($b) <=> strlen($a));
        $patron = '/(?<![A-Za-z0-9_-])('.implode('|', array_map(fn ($u) => preg_quote($u, '/'), $claves)).')(?![A-Za-z0-9_-])/';

        return preg_replace_callback($patron, fn (array $m) => $uids[$m[1]], $texto);
    }
}
