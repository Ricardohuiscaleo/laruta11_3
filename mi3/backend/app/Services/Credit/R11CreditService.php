<?php

namespace App\Services\Credit;

use App\Models\R11CreditTransaction;
use App\Models\Usuario;

class R11CreditService
{
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
