<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Payroll\LiquidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $liquidacionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $mes = $request->query('mes', now()->format('Y-m'));

        $secciones = [];
        $granTotal = 0;

        $liqRuta11 = $this->liquidacionService->calcular($personal, $mes, 'ruta11');
        if ($liqRuta11['sueldo_base'] > 0 || $liqRuta11['total'] != 0) {
            $secciones[] = $liqRuta11;
            $granTotal += $liqRuta11['total'];
        }

        $liqSeguridad = $this->liquidacionService->calcular($personal, $mes, 'seguridad');
        if ($liqSeguridad['sueldo_base'] > 0 || $liqSeguridad['total'] != 0) {
            $secciones[] = $liqSeguridad;
            $granTotal += $liqSeguridad['total'];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'mes' => $mes,
                'secciones' => $secciones,
                'gran_total' => $granTotal,
            ],
        ]);
    }
}
