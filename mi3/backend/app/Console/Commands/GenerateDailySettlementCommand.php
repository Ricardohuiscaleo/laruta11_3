<?php

namespace App\Console\Commands;

use App\Services\Delivery\SettlementService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateDailySettlementCommand extends Command
{
    protected $signature = 'delivery:generate-daily-settlement {--date= : Fecha YYYY-MM-DD, default=hoy}';
    protected $description = 'Genera el settlement diario de delivery para ARIAKA';

    public function __construct(private SettlementService $settlementService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dateOption = $this->option('date');

        if ($dateOption !== null) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay();
            } catch (\Throwable $e) {
                $this->error("Formato de fecha inválido: '{$dateOption}'. Use YYYY-MM-DD.");
                return self::FAILURE;
            }
        } else {
            $date = Carbon::today();
        }

        try {
            $settlement = $this->settlementService->generateDailySettlement($date);

            if ($settlement->total_orders_delivered === 0) {
                $this->info("Sin pedidos entregados para {$date->format('Y-m-d')}");
            } else {
                $this->info(
                    "Settlement generado para {$date->format('Y-m-d')}: " .
                    "{$settlement->total_orders_delivered} pedidos, " .
                    "\${$settlement->total_delivery_fees}"
                );
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Log::error('[GenerateDailySettlementCommand] Error al generar settlement', [
                'date'  => $date->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            $this->error("Error al generar settlement: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
