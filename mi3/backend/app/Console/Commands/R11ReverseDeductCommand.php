<?php

namespace App\Console\Commands;

use App\Models\AjusteSueldo;
use App\Models\R11CreditTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class R11ReverseDeductCommand extends Command
{
    protected $signature = 'mi3:r11-reverse-deduct';
    protected $description = 'Revierte el último descuento automático R11 (nómina)';

    public function handle(): int
    {
        $this->info('Buscando transacciones refund del auto-deduct...');

        $refunds = R11CreditTransaction::where('type', 'refund')
            ->where('description', 'like', 'Descuento nómina%')
            ->whereDate('created_at', now()->toDateString())
            ->get();

        if ($refunds->isEmpty()) {
            $this->warn('No se encontraron transacciones refund de hoy. Revisando última ejecución...');

            $lastRefund = R11CreditTransaction::where('type', 'refund')
                ->where('description', 'like', 'Descuento nómina%')
                ->latest('id')
                ->first();

            if (!$lastRefund) {
                $this->error('No hay transacciones refund del auto-deduct en absoluto. Nada que revertir.');
                return self::FAILURE;
            }

            $this->warn("Último refund encontrado: #{$lastRefund->id} del {$lastRefund->created_at}");
            $this->warn('Ejecuta con --force si quieres revertir igualmente.');
            return self::FAILURE;
        }

        $count = 0;
        $totalRestored = 0;

        foreach ($refunds as $refund) {
            DB::transaction(function () use ($refund, &$count, &$totalRestored) {
                $user = $refund->usuario;
                if (!$user) {
                    $this->warn("  ✗ Usuario #{$refund->user_id} no existe, saltando...");
                    $refund->delete();
                    return;
                }

                $amount = $refund->amount;

                // Restore credit used
                $user->credito_r11_usado = $amount;
                $user->credito_r11_bloqueado = 1;
                $user->fecha_ultimo_pago_r11 = null;
                $user->save();

                // Delete salary adjustment
                $personalId = $user->personal?->id;
                if ($personalId) {
                    $deleted = AjusteSueldo::where('personal_id', $personalId)
                        ->where('monto', -$amount)
                        ->where('concepto', $refund->description)
                        ->delete();

                    if ($deleted) {
                        $this->line("  ✓ Ajuste sueldo eliminado (personal_id: {$personalId})");
                    } else {
                        $this->warn("  ⚠ No se encontró ajuste sueldo para personal_id {$personalId}, monto -{$amount}");
                    }
                }

                // Delete refund transaction
                $refund->delete();

                $count++;
                $totalRestored += $amount;
                $this->line("  ✓ {$user->nombre}: \${$amount} restaurado");
            });
        }

        $this->info("Reversión completada: {$count} usuarios, \${$totalRestored} restaurados.");
        return self::SUCCESS;
    }
}
