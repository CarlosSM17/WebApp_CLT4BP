<?php

namespace App\Console\Commands;

use App\Models\BankItem;
use App\Services\Banco\VerificadorBanco;
use Illuminate\Console\Command;
use Throwable;

/** Ejecuta en Piston todos los programas del banco, en cada lenguaje donde se ofrecen. Requiere el ejecutor encendido. */
class VerificarBanco extends Command
{
    protected $signature = 'banco:verificar {--lenguaje= : c, cpp o python (por omisión, todos)} {--tema= : solo los ítems de ese tema}';

    protected $description = 'Comprueba ejecutándolos los programas, predicciones y Parsons del banco';

    public function handle(VerificadorBanco $verificador): int
    {
        $consulta = BankItem::with('tema')->orderBy('id');
        if ($tema = $this->option('tema')) {
            $consulta->whereHas('tema', fn ($q) => $q->where('clave', $tema));
        }

        $revisados = 0;
        $problemas = [];
        foreach ($consulta->get() as $item) {
            $lenguajes = $this->option('lenguaje') ? array_intersect($item->lenguajes, [$this->option('lenguaje')]) : $item->lenguajes;
            foreach ($lenguajes as $lenguaje) {
                if ($item->tipo !== 'programacion' && $item->verificacion === null) {
                    continue;
                }
                $revisados++;
                try {
                    $problema = $verificador->problema($item, $lenguaje);
                } catch (Throwable $e) {
                    $problema = 'no se pudo ejecutar: '.$e->getMessage();
                }
                if ($problema) {
                    $problemas[] = "{$item->codigo} [{$lenguaje}]: {$problema}";
                    $this->error(end($problemas));
                }
            }
        }

        $this->info("Revisadas {$revisados} ejecuciones de ítems; ".count($problemas).' con problemas.');

        return $problemas === [] ? self::SUCCESS : self::FAILURE;
    }
}
