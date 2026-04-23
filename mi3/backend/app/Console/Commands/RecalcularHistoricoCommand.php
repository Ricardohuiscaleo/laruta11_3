<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CierreDiario\CierreDiarioService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RecalcularHistoricoCommand extends Command
{
    protected $signature = 'mi3:cierre-recalcular-historico
                            {--desde= : Fecha inicio (YYYY-MM-DD). Si no se indica, usa la primera orden en tuu_orders}
                            {--hasta= : Fecha fin (YYYY-MM-DD). Default: ayer}';

    protected $description = 'Recalcula todos los registros históricos de capital_trabajo día por día';

    public function handle(CierreDiarioService $service): int
    {
        $desde = $this->option('desde')
            ? Carbon::parse($this->option('desde'))
            : Carbon::parse(
                DB::table('tuu_orders')->min('created_at') ?? now()->subMonth()->toDateString()
            )->startOfDay();

        $hasta = $this->option('hasta')
            ? Carbon::parse($this->option('hasta'))
            : Carbon::yesterday();

        $totalDias = $desde->diffInDays($hasta) + 1;
        $this->info("Recalculando {$totalDias} días: {$desde->toDateString()} → {$hasta->toDateString()}");

        $bar = $this->output->createProgressBar($totalDias);
        $bar->start();

        $cursor = $desde->copy();
        $errores = 0;

        while ($cursor->lte($hasta)) {
            try {
                $service->cerrar($cursor->toDateString());
            } catch (\Exception $e) {
                $errores++;
                $this->newLine();
                $this->error("Error en {$cursor->toDateString()}: {$e->getMessage()}");
            }

            $bar->advance();
            $cursor->addDay();
        }

        $bar->finish();
        $this->newLine(2);

        if ($errores > 0) {
            $this->warn("Completado con {$errores} errores.");
            return self::FAILURE;
        }

        $this->info('Recálculo histórico completado sin errores.');
        return self::SUCCESS;
    }
}
