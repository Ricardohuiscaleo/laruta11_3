<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\CierreDiario\CierreDiarioService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CierreDiarioCommand extends Command
{
    protected $signature = 'mi3:cierre-diario';

    protected $description = 'Genera el cierre diario de capital de trabajo para el turno que acaba de terminar';

    public function handle(CierreDiarioService $service): int
    {
        // A las 04:15 Chile el turno que terminó corresponde a "ayer"
        $fecha = Carbon::now('America/Santiago')->subHours(5)->toDateString();

        $this->info("Ejecutando cierre diario para {$fecha}...");

        $result = $service->cerrar($fecha);

        if ($result['success']) {
            $data = $result['data'];
            $this->info("Cierre completado: saldo_final = $" . number_format((float) $data->saldo_final, 0, ',', '.'));

            if (!empty($result['warnings'])) {
                foreach ($result['warnings'] as $w) {
                    $this->warn("⚠ {$w}");
                }
            }

            return self::SUCCESS;
        }

        $this->error('Error al ejecutar cierre diario');

        return self::FAILURE;
    }
}
