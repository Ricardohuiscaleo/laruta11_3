<?php

namespace App\Console\Commands;

use App\Models\NotificacionMi3;
use App\Models\Personal;
use App\Services\Checklist\AttendanceService;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectAbsencesCommand extends Command
{
    protected $signature = 'mi3:detect-absences';
    protected $description = 'Detectar inasistencias del día y aplicar descuento de $40.000';

    public function __construct(
        private AttendanceService $attendanceService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $fecha = now()->subDay()->format('Y-m-d');
        $this->info("Detectando inasistencias para {$fecha} (ayer)...");

        $result = $this->attendanceService->detectarAusencias($fecha);

        $this->info("Inasistencias detectadas: {$result['total']}");

        foreach ($result['ausentes'] as $ausente) {
            $this->line("  • {$ausente['nombre']} (personal_id: {$ausente['personal_id']}) — descuento $40.000");
        }

        // Send notifications
        $this->enviarNotificaciones($result['ausentes']);

        $this->info('Detección de inasistencias finalizada.');

        return self::SUCCESS;
    }

    private function enviarNotificaciones(array $ausentes): void
    {
        if (empty($ausentes)) {
            return;
        }

        try {
            $pushService = app(PushNotificationService::class);
        } catch (\Throwable $e) {
            Log::warning('PushNotificationService no disponible, omitiendo notificaciones push: ' . $e->getMessage());
            $pushService = null;
        }

        // Find admin personal_id for in-app notification
        $admin = Personal::where('activo', 1)
            ->where('rol', 'like', '%administrador%')
            ->first();

        foreach ($ausentes as $ausente) {
            // Push notification to absent worker (Req 10.3)
            if ($pushService) {
                try {
                    $pushService->enviar(
                        $ausente['personal_id'],
                        '⚠️ Inasistencia registrada',
                        'Se registró tu inasistencia del día. Se aplicó un descuento de $40.000 en tu liquidación.',
                        '/dashboard',
                    );
                } catch (\Throwable $e) {
                    Log::warning("Error enviando push a personal_id {$ausente['personal_id']}: " . $e->getMessage());
                }
            }

            // In-app notification to admin (Req 10.4)
            if ($admin) {
                try {
                    NotificacionMi3::create([
                        'personal_id' => $admin->id,
                        'tipo' => 'asistencia',
                        'titulo' => 'Inasistencia detectada',
                        'mensaje' => "{$ausente['nombre']} no asistió hoy. Se aplicó descuento de \$40.000.",
                        'referencia_id' => $ausente['personal_id'],
                        'referencia_tipo' => 'personal',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning("Error creando notificación admin para ausente {$ausente['personal_id']}: " . $e->getMessage());
                }
            }
        }

        $this->info('Notificaciones de inasistencia enviadas.');
    }
}
