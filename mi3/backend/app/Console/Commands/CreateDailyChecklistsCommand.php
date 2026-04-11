<?php

namespace App\Console\Commands;

use App\Models\NotificacionMi3;
use App\Models\Personal;
use App\Services\Checklist\ChecklistService;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CreateDailyChecklistsCommand extends Command
{
    protected $signature = 'mi3:create-daily-checklists';
    protected $description = 'Crear checklists diarios (apertura + cierre) para trabajadores con turno hoy';

    public function __construct(
        private ChecklistService $checklistService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha = now()->format('Y-m-d');
        $this->info("Creando checklists diarios para {$fecha}...");

        $result = $this->checklistService->crearChecklistsDiarios($fecha);

        $this->info("Checklists creados: {$result['created']}, omitidos (duplicados): {$result['skipped']}");

        // Send push notifications to workers with created checklists
        $this->enviarNotificaciones($fecha, $result['created']);

        $this->info('Creación de checklists diarios finalizada.');

        return self::SUCCESS;
    }

    private function enviarNotificaciones(string $fecha, int $created): void
    {
        if ($created === 0) {
            return;
        }

        try {
            $pushService = app(PushNotificationService::class);
        } catch (\Throwable $e) {
            Log::warning('PushNotificationService no disponible, omitiendo notificaciones push: ' . $e->getMessage());
            return;
        }

        // Get workers who have checklists for today
        $personalIds = \App\Models\Checklist::whereDate('scheduled_date', $fecha)
            ->distinct()
            ->pluck('personal_id')
            ->filter();

        $sent = 0;
        foreach ($personalIds as $personalId) {
            try {
                $pushService->enviar(
                    $personalId,
                    '📋 Checklists del día',
                    'Tienes checklists pendientes para hoy. ¡No olvides completarlos!',
                    '/dashboard/checklist',
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Error enviando push a personal_id {$personalId}: " . $e->getMessage());
            }
        }

        $this->info("Notificaciones push enviadas: {$sent}");
    }
}
