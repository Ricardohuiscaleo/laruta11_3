<?php

namespace App\Console\Commands;

use App\Services\Loan\LoanService;
use Illuminate\Console\Command;

class LoanAutoDeductCommand extends Command
{
    protected $signature = 'mi3:loan-auto-deduct';
    protected $description = 'Descuento automático de adelantos de sueldo en nómina (día 1)';

    public function __construct(
        private LoanService $loanService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Iniciando descuento automático de adelantos de sueldo...');

        $result = $this->loanService->procesarDescuentosMensuales();

        $resultados = $result['resultados'];
        $errores = $result['errores'];

        // Log errors
        foreach ($errores as $error) {
            $this->error("✗ Adelanto #{$error['prestamo_id']}: {$error['error']}");
        }

        // Log results
        if (empty($resultados)) {
            $this->info('No hay adelantos pendientes de descuento este mes.');
        } else {
            $this->info('Adelantos procesados: ' . count($resultados));
            foreach ($resultados as $r) {
                $monto = number_format($r['monto'], 0, ',', '.');
                $this->line("  • Adelanto #{$r['prestamo_id']} (personal_id: {$r['personal_id']}): \${$monto} — estado: {$r['estado_final']}");
            }
        }

        // Summary
        if (!empty($errores)) {
            $this->warn('⚠ Errores: ' . count($errores));
        }

        $this->info('Descuento automático de adelantos finalizado.');

        return empty($errores) ? self::SUCCESS : self::FAILURE;
    }
}
