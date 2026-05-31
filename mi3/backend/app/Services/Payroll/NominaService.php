<?php

namespace App\Services\Payroll;

use App\Models\PagoNomina;
use App\Models\Personal;
use App\Models\PresupuestoNomina;

class NominaService
{
    public function __construct(
        private LiquidacionService $liquidacionService,
    ) {}

    /**
     * Get payroll summary for a given month, grouped by cost center.
     */
    public function getResumen(string $mes): array
    {
        $personal = Personal::where('activo', 1)->get();

        $ruta11 = [];
        $seguridad = [];

        foreach ($personal as $p) {
            $liqRuta11 = $this->liquidacionService->calcular($p, $mes, 'ruta11');
            $liqSeguridad = $this->liquidacionService->calcular($p, $mes, 'seguridad');

            if ($liqRuta11['sueldo_base'] > 0 || $liqRuta11['total'] != 0) {
                $ruta11[] = ['personal' => $p, 'liquidacion' => $liqRuta11];
            }
            if ($liqSeguridad['sueldo_base'] > 0 || $liqSeguridad['total'] != 0) {
                $seguridad[] = ['personal' => $p, 'liquidacion' => $liqSeguridad];
            }
        }

        $mesDate = $mes . '-01';

        $pagosRuta11 = PagoNomina::where('mes', $mesDate)
            ->where('centro_costo', 'ruta11')
            ->get();
        $pagosSeguridad = PagoNomina::where('mes', $mesDate)
            ->where('centro_costo', 'seguridad')
            ->get();

        $presupuestoRuta11 = PresupuestoNomina::where('mes', $mesDate)
            ->where('centro_costo', 'ruta11')
            ->value('monto') ?? 0;
        $presupuestoSeguridad = PresupuestoNomina::where('mes', $mesDate)
            ->where('centro_costo', 'seguridad')
            ->value('monto') ?? 0;

        return [
            'ruta11' => [
                'personal' => $ruta11,
                'pagos' => $pagosRuta11,
                'presupuesto' => $presupuestoRuta11,
            ],
            'seguridad' => [
                'personal' => $seguridad,
                'pagos' => $pagosSeguridad,
                'presupuesto' => $presupuestoSeguridad,
            ],
        ];
    }
}
