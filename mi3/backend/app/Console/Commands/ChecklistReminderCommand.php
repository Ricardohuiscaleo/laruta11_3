<?php

namespace App\Console\Commands;

use App\Models\Checklist;
use App\Models\Personal;
use App\Services\Notification\PushNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChecklistReminderCommand extends Command
{
    protected $signature = 'mi3:checklist-reminder';
    protected $description = 'Enviar push notification a las 6pm si hay checklists pendientes';

    public function handle(): void
    {
        $hoy = now()->format('Y-m-d');

        // Buscar checklists pendientes de hoy agrupados por personal_id
        $pendientes = Checklist::where('scheduled_date', $hoy)
            ->whereIn('status', ['pending', 'active'])
            ->whereNotNull('personal_id')
            ->select('personal_id', DB::raw('COUNT(*) as total'))
            ->groupBy('personal_id')
            ->get();

        if ($pendientes->isEmpty()) {
            $this->info('No hay checklists pendientes para hoy.');
            return;
        }

        $pushService = app(PushNotificationService::class);
        $enviados = 0;

        foreach ($pendientes as $p) {
            try {
                $personal = Personal::find($p->personal_id);
                if (!$personal) continue;

                $pushService->enviar(
                    $p->personal_id,
                    '📋 Checklist pendiente',
                    "Tienes {$p->total} checklist(s) sin completar hoy. ¡No olvides hacerlo!",
                    '/dashboard/checklist',
                    'normal'
                );
                $enviados++;
                $this->info("Push enviado a {$personal->nombre} ({$p->total} pendientes)");
            } catch (\Exception $e) {
                $this->warn("Error enviando push a personal_id={$p->personal_id}: {$e->getMessage()}");
            }
        }

        $this->info("Total: {$enviados} notificaciones enviadas.");
    }
}
