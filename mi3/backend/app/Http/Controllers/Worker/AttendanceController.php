<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Payroll\LiquidacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $liquidacionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $mes = $request->query('mes', now()->format('Y-m'));

        $resumen = [];

        $liqRuta11 = $this->liquidacionService->calcular($personal, $mes, 'ruta11');
        if ($liqRuta11['sueldo_base'] > 0 || $liqRuta11['dias_normales'] > 0 || $liqRuta11['reemplazos_hechos'] > 0) {
            $resumen['ruta11'] = [
                'dias_normales' => $liqRuta11['dias_normales'],
                'reemplazos_realizados' => $liqRuta11['reemplazos_hechos'],
                'dias_trabajados' => $liqRuta11['dias_trabajados'],
                'dias_reemplazado' => $liqRuta11['dias_reemplazados'],
            ];
        } else {
            $resumen['ruta11'] = null;
        }

        $liqSeguridad = $this->liquidacionService->calcular($personal, $mes, 'seguridad');
        if ($liqSeguridad['sueldo_base'] > 0 || $liqSeguridad['dias_normales'] > 0 || $liqSeguridad['reemplazos_hechos'] > 0) {
            $resumen['seguridad'] = [
                'dias_normales' => $liqSeguridad['dias_normales'],
                'reemplazos_realizados' => $liqSeguridad['reemplazos_hechos'],
                'dias_trabajados' => $liqSeguridad['dias_trabajados'],
                'dias_reemplazado' => $liqSeguridad['dias_reemplazados'],
            ];
        } else {
            $resumen['seguridad'] = null;
        }

        $diasMes = Carbon::parse($mes . '-01')->daysInMonth;

        return response()->json([
            'success' => true,
            'data' => [
                'mes' => $mes,
                'resumen' => $resumen,
                'dias_mes' => $diasMes,
            ],
        ]);
    }
}
