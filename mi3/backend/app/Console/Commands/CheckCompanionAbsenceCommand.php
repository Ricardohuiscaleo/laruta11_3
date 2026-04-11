<?php

namespace App\Console\Commands;

use App\Services\Checklist\AttendanceService;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckCompanionAbsenceCommand extends Command
{
    protected $signature = 'mi3:check-companion-absence';
    protected $description = 'Detectar compañero ausente y habilitar checklist virtual para el trabajador presente';

    public function __construct(
        private AttendanceService $attendanceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha = now()->format('Y-m-d');
        $this->info("Verificando compañeros ausentes para {$fecha}...");

        $result = $this->attendanceService->detectarCompaneroAusente($fecha);

        $this->info("Checklists virtuales habilitados: {$result['total']}");

        foreach ($result['habilitados'] as $h) {
            $this->line("  • personal_id: {$h['personal_id']} (compañero ausente: {$h['ausente_id']}) — virtual #{$h['virtual_id']}");
        }

        // Send push notifications to affected workers
        $this->enviarNotificaciones($result['habilitados']);

        $this->info('Verificación de compañeros ausentes finalizada.');

        return self::SUCCESS;
    }

    private function enviarNotificaciones(array $habilitados): void
    {
        if (empty($habilitados)) {
            return;
        }

        try {
            $pushService = app(PushNotificationService::class);
        } catch (\Throwable $e) {
            Log::warning('PushNotificationService no disponible, omitiendo notificaciones push: ' . $e->getMessage());
            return;
        }

        foreach ($habilitados as $h) {
            try {
                $pushService->enviar(
                    $h['personal_id'],
                    '📋 Checklist virtual disponible',
                    'Tu compañero/a de turno no asistió hoy. Debes completar el checklist virtual para registrar tu asistencia.',
                    '/dashboard/checklist',
                );
            } catch (\Throwable $e) {
                Log::warning("Error enviando push a personal_id {$h['personal_id']}: " . $e->getMessage());
            }
        }

        $this->info('Notificaciones de checklist virtual enviadas.');
    }
}
