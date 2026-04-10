<?php

namespace App\Services\Credit;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\R11CreditTransaction;
use App\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class R11CreditService
{
    /**
     * Auto-deduct R11 credit from all eligible workers' payroll.
     *
     * Runs on the 1st of each month via scheduler.
     * Creates salary adjustments, refund transactions, and resets credit.
     */
    public function autoDeduct(): array
    {
        $mes = now()->format('Y-m');
        $mesNombre = now()->locale('es')->monthName;
        $categoriaR11 = AjusteCategoria::where('slug', 'descuento_credito_r11')->first();

        $deudores = Usuario::where('es_credito_r11', 1)
            ->where('credito_r11_usado', '>', 0)
            ->whereHas('personal', fn($q) => $q->where('activo', 1))
            ->with('personal')
            ->get();

        $resultados = [];
        $advertencias = [];

        foreach ($deudores as $usuario) {
            if (!$usuario->personal) {
                $advertencias[] = "Usuario {$usuario->id} ({$usuario->nombre}) sin personal vinculado";
                continue;
            }

            $monto = (float) $usuario->credito_r11_usado;

            DB::transaction(function () use ($usuario, $monto, $mes, $mesNombre, $categoriaR11) {
                // 1. Create salary adjustment (negative = deduction)
                AjusteSueldo::create([
                    'personal_id' => $usuario->personal->id,
                    'mes' => $mes . '-01',
                    'monto' => -$monto,
                    'concepto' => "Descuento Crédito R11 - {$mesNombre}",
                    'categoria_id' => $categoriaR11?->id,
                ]);

                // 2. Create R11 refund transaction
                R11CreditTransaction::create([
                    'user_id' => $usuario->id,
                    'amount' => $monto,
                    'type' => 'refund',
                    'description' => "Descuento nómina {$mesNombre}",
                ]);

                // 3. Reset credit usage and unblock
                $usuario->update([
                    'credito_r11_usado' => 0,
                    'fecha_ultimo_pago_r11' => now()->toDateString(),
                    'credito_r11_bloqueado' => 0,
                ]);
            });

            $resultados[] = [
                'nombre' => $usuario->nombre,
                'monto' => $monto,
                'personal_id' => $usuario->personal->id,
            ];
        }

        return [
            'resultados' => $resultados,
            'advertencias' => $advertencias,
        ];
    }

    /**
     * Get credit information for a user.
     */
    public function getCreditInfo(Usuario $usuario): array
    {
        return [
            'activo' => (bool) $usuario->es_credito_r11,
            'aprobado' => (bool) $usuario->credito_r11_aprobado,
            'bloqueado' => (bool) $usuario->credito_r11_bloqueado,
            'limite' => (float) $usuario->limite_credito_r11,
            'usado' => (float) $usuario->credito_r11_usado,
            'disponible' => (float) ($usuario->limite_credito_r11 - $usuario->credito_r11_usado),
            'relacion_r11' => $usuario->relacion_r11,
            'fecha_aprobacion' => $usuario->fecha_aprobacion_r11,
        ];
    }

    /**
     * Get credit transaction history for a user.
     */
    public function getTransactions(int $userId): array
    {
        return R11CreditTransaction::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get()
            ->toArray();
    }
}
