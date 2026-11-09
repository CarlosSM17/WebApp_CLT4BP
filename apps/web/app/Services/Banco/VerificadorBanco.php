<?php

namespace App\Services\Banco;

use App\Domain\Pruebas\ComparadorSalida;
use App\Models\BankItem;
use App\Services\Codigo\EjecutorCodigo;
use App\Services\Codigo\EvaluadorCasos;

/**
 * Ejecuta lo que un ítem del banco afirma: que la solución pasa sus casos y que el programa de una predicción o de un
 * Parsons imprime lo que dice su clave. Un ítem con la clave mal calificaría mal a todos los estudiantes.
 */
final class VerificadorBanco
{
    public function __construct(
        private readonly EjecutorCodigo $ejecutor,
        private readonly EvaluadorCasos $evaluador,
    ) {}

    /** @return string|null qué falló, o null si está bien. Un ítem sin código no tiene nada que ejecutar. */
    public function problema(BankItem $item, string $lenguaje): ?string
    {
        if ($item->tipo === 'programacion') {
            return $this->problemaProgramacion($item, $lenguaje);
        }
        if ($item->verificacion === null) {
            return null;
        }

        $v = $item->verificacion;
        $r = $this->ejecutor->ejecutar($lenguaje, $v['programa'], $v['entrada'] ?? '');
        if (! $r->compilo) {
            return 'no compila: '.mb_substr($r->errores, 0, 300);
        }
        if ($r->excedioLimite || $r->codigo !== 0) {
            return "terminó con código {$r->codigo}: ".mb_substr($r->errores, 0, 300);
        }
        if (! ComparadorSalida::iguales($r->salida, $v['salida'])) {
            return 'la clave dice '.json_encode($v['salida']).' y el programa imprime '.json_encode($r->salida);
        }

        return null;
    }

    private function problemaProgramacion(BankItem $item, string $lenguaje): ?string
    {
        $r = $this->evaluador->evaluar($lenguaje, (string) $item->solucion, $item->casos_prueba ?? []);
        if ($r['error_compilacion'] !== null) {
            return 'no compila: '.mb_substr($r['error_compilacion'], 0, 300);
        }
        if ($r['total'] === 0 || $r['aprobados'] !== $r['total']) {
            $fallidos = collect($r['casos'])->where('aprobado', false)->pluck('caso')->implode(', ');

            return "pasa {$r['aprobados']} de {$r['total']} casos (fallan: {$fallidos})";
        }

        return null;
    }
}
