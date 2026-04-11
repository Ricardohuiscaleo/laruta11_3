<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Turno;
use App\Services\Loan\LoanService;
use App\Services\Payroll\LiquidacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly LiquidacionService $liquidacionService,
        private readonly LoanService $loanService,
    ) {}

    /**
     * GET /api/v1/worker/dashboard-summary
     *
     * Returns: sueldo, préstamo activo, descuentos por categoría, reemplazos del mes.
     */
    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $mes = now()->format('Y-m');

        // 1. Sueldo — use LiquidacionService
        $liquidacion = $this->liquidacionService->calcular($personal, $mes);
        $sueldo = [
            'total' => $liquidacion['total'],
            'mes' => $mes,
        ];

        // 2. Préstamo activo
        $prestamoActivo = $this->loanService->getPrestamoActivo($personal->id);
        if ($prestamoActivo) {
            $montoCuota = (int) round($prestamoActivo->monto_aprobado / $prestamoActivo->cuotas);
            $cuotasRestantes = $prestamoActivo->cuotas - $prestamoActivo->cuotas_pagadas;
            $montoPendiente = $prestamoActivo->monto_aprobado - ($prestamoActivo->cuotas_pagadas * $montoCuota);

            $prestamo = [
                'tiene_activo' => true,
                'monto_pendiente' => $montoPendiente,
                'cuotas_restantes' => $cuotasRestantes,
                'monto_cuota' => $montoCuota,
            ];
        } else {
            $prestamo = [
                'tiene_activo' => false,
                'monto_pendiente' => 0,
                'cuotas_restantes' => 0,
                'monto_cuota' => 0,
            ];
        }

        // 3. Descuentos del mes — ajustes negativos agrupados por categoría
        $ajustesNegativos = AjusteSueldo::where('personal_id', $personal->id)
            ->where('mes', $mes . '-01')
            ->where('monto', '<', 0)
            ->get();

        $totalDescuentos = (int) $ajustesNegativos->sum('monto');
        $porCategoria = [];

        $categorias = AjusteCategoria::all()->keyBy('id');

        foreach ($ajustesNegativos as $ajuste) {
            $cat = $categorias->get($ajuste->categoria_id);
            $slug = $cat ? $cat->slug : 'otros';

            if (!isset($porCategoria[$slug])) {
                $porCategoria[$slug] = 0;
            }
            $porCategoria[$slug] += (int) $ajuste->monto;
        }

        $descuentos = [
            'total' => $totalDescuentos,
            'por_categoria' => $porCategoria,
        ];

        // 4. Reemplazos del mes
        $inicioMes = Carbon::parse($mes . '-01')->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->endOfDay();

        $tiposReemplazo = ['reemplazo', 'reemplazo_seguridad'];

        // Realizados: worker is the reemplazante (reemplazado_por = personal_id)
        $realizados = Turno::where('reemplazado_por', $personal->id)
            ->whereIn('tipo', $tiposReemplazo)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get();

        // Recibidos: worker is the titular (personal_id = personal_id) and tipo is reemplazo
        $recibidos = Turno::where('personal_id', $personal->id)
            ->whereIn('tipo', $tiposReemplazo)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get();

        $reemplazos = [
            'realizados' => [
                'cantidad' => $realizados->count(),
                'monto' => (int) $realizados->sum(fn($t) => $t->monto_reemplazo ?? 20000),
            ],
            'recibidos' => [
                'cantidad' => $recibidos->count(),
                'monto' => (int) $recibidos->sum(fn($t) => $t->monto_reemplazo ?? 20000),
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'sueldo' => $sueldo,
                'prestamo' => $prestamo,
                'descuentos' => $descuentos,
                'reemplazos' => $reemplazos,
            ],
        ]);
    }
}
