<?php

namespace App\Console\Commands;

use App\Models\DailySettlement;
use App\Models\Personal;
use App\Services\Notification\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingSettlementsCommand extends Command
{
    protected $signature = 'delivery:check-pending-settlements';
    protected $description = 'Verifica settlements pendientes y envía alertas push a admins';

    public function handle(): int
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $pendingSettlements = DailySettlement::where('status', 'pending')
            ->where('total_delivery_fees', '>', 0)
            ->whereDate('settlement_date', $yesterday)
            ->get();

        if ($pendingSettlements->isEmpty()) {
            $this->info('No hay settlements pendientes');
            return self::SUCCESS;
        }

        $admins = Personal::where('activo', true)
            ->get()
            ->filter(fn(Personal $p) => $p->isAdmin());

        if ($admins->isEmpty()) {
            $this->warn('No se encontraron admins activos para notificar');
            return self::SUCCESS;
        }

        try {
            $pushService = app(PushNotificationService::class);
        } catch (\Throwable $e) {
            Log::warning('[CheckPendingSettlementsCommand] PushNotificationService no disponible: ' . $e->getMessage());
            $this->warn('PushNotificationService no disponible, omitiendo notificaciones push');
            return self::SUCCESS;
        }

        foreach ($pendingSettlements as $settlement) {
            $dateLabel = Carbon::parse($settlement->settlement_date)->format('Y-m-d');
            $total     = number_format($settlement->total_delivery_fees, 0, ',', '.');

            foreach ($admins as $admin) {
                try {
                    $pushService->enviar(
                        $admin->id,
                        '💰 Settlement delivery pendiente',
                        "Hay un pago pendiente a ARIAKA por \${$total} del {$dateLabel}.",
                        '/admin/delivery/settlements',
                        'high'
                    );
                } catch (\Throwable $e) {
                    Log::warning("[CheckPendingSettlementsCommand] Error enviando push a admin {$admin->id}: " . $e->getMessage());
                }
            }

            $this->info("Alerta enviada a {$admins->count()} admins por settlement pendiente de {$dateLabel}");
        }

        return self::SUCCESS;
    }
}
