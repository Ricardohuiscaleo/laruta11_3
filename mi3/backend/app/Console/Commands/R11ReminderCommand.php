<?php

namespace App\Console\Commands;

use App\Models\NotificacionMi3;
use App\Models\Usuario;
use Illuminate\Console\Command;

class R11ReminderCommand extends Command
{
    protected $signature = 'mi3:r11-reminder';
    protected $description = 'Recordatorio de pago R11 a trabajadores con deuda (día 28)';

    public function handle(): int
    {
        $this->info('Enviando recordatorios R11...');

        $mes = now()->locale('es')->monthName;

        $deudores = Usuario::where('es_credito_r11', 1)
            ->where('credito_r11_usado', '>', 0)
            ->whereHas('personal', fn($q) => $q->where('activo', 1))
            ->with('personal')
            ->get();

        $count = 0;

        foreach ($deudores as $usuario) {
            if (!$usuario->personal) {
                $this->warn("⚠ Usuario {$usuario->id} ({$usuario->nombre}) sin personal vinculado — omitido");
                continue;
            }

            $monto = number_format((float) $usuario->credito_r11_usado, 0, ',', '.');

            NotificacionMi3::create([
                'personal_id' => $usuario->personal->id,
                'tipo' => 'credito',
                'titulo' => 'Recordatorio: Deuda Crédito R11',
                'mensaje' => "Tienes una deuda de \${$monto} en crédito R11. Se descontará automáticamente de tu liquidación de {$mes}.",
                'referencia_id' => $usuario->id,
                'referencia_tipo' => 'usuario',
            ]);

            $count++;
        }

        $this->info("Recordatorios enviados: {$count}");

        return self::SUCCESS;
    }
}
