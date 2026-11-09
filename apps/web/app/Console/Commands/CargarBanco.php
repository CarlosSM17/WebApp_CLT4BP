<?php

namespace App\Console\Commands;

use App\Models\BankItem;
use App\Models\BankTopic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el catálogo de resources/banco (generado por banco/generar.py). Se puede repetir: actualiza lo que cambió y
 * quita lo que ya no está. Los temas propios de los instructores y las copias que ya tienen los cursos no se tocan.
 * El arranque en Railway y `desplegar.sh` lo corren siempre, junto a las migraciones: cada versión trae su catálogo.
 */
class CargarBanco extends Command
{
    protected $signature = 'banco:cargar {--archivo= : ruta del JSON (por omisión, resources/banco/banco.json)}';

    protected $description = 'Carga o actualiza el catálogo de temas e ítems del banco';

    public function handle(): int
    {
        $ruta = $this->option('archivo') ?: resource_path('banco/banco.json');
        $banco = json_decode((string) @file_get_contents($ruta), true);
        if (! is_array($banco) || ! isset($banco['temas'], $banco['items'])) {
            $this->error("No es un banco válido: {$ruta}");

            return self::FAILURE;
        }

        DB::transaction(function () use ($banco) {
            $ids = [];
            foreach ($banco['temas'] as $t) {
                $tema = BankTopic::updateOrCreate(['clave' => $t['clave']], [
                    'owner_id' => null,
                    'titulo' => $t['titulo'],
                    'descripcion' => $t['descripcion'],
                    'tipo' => $t['tipo'],
                    'evaluacion' => $t['evaluacion'],
                    'prerrequisitos' => $t['prerrequisitos'],
                    'lenguajes' => $t['lenguajes'],
                    'orden' => $t['orden'],
                    'horas' => $t['horas'],
                    'complejidad' => $t['complejidad'] ?? null,
                ]);
                $ids[$t['clave']] = $tema->id;
            }

            foreach ($banco['items'] as $i) {
                BankItem::updateOrCreate(['codigo' => $i['codigo']], [
                    'bank_topic_id' => $ids[$i['tema']],
                    'tipo' => $i['tipo'],
                    'nivel' => $i['nivel'],
                    'lenguajes' => $i['lenguajes'],
                    'enunciado' => $i['enunciado'],
                    'clave' => $i['clave'] ?? null,
                    'casos_prueba' => $i['casos_prueba'] ?? null,
                    'solucion' => $i['solucion'] ?? null,
                    'verificacion' => $i['verificacion'] ?? null,
                ]);
            }

            BankItem::whereNotIn('codigo', array_column($banco['items'], 'codigo'))->delete();
            BankTopic::whereNull('owner_id')->whereNotIn('clave', array_column($banco['temas'], 'clave'))->delete();
        });

        $this->info('Banco cargado: '.count($banco['temas']).' temas y '.count($banco['items']).' ítems.');

        return self::SUCCESS;
    }
}
