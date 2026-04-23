<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CierreDiario\CierreDiarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CapitalTrabajoController extends Controller
{
    public function __construct(
        private readonly CierreDiarioService $cierreDiarioService,
    ) {}

    /**
     * Resumen mensual de capital de trabajo.
     * GET /api/v1/admin/capital-trabajo?mes=2026-04
     */
    public function resumenMensual(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));

        if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
            return response()->json([
                'success' => false,
                'error' => 'Formato de mes inválido. Use YYYY-MM.',
            ], 422);
        }

        $data = $this->cierreDiarioService->getResumenMensual($mes);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Cierre manual de un día específico.
     * POST /api/v1/admin/capital-trabajo/cierre
     */
    public function cierreManual(Request $request): JsonResponse
    {
        $request->validate([
            'fecha' => 'required|date_format:Y-m-d',
        ]);

        $result = $this->cierreDiarioService->cerrar($request->input('fecha'));

        return response()->json([
            'success' => $result['success'],
            'data' => $result['data'],
            'warnings' => $result['warnings'],
        ]);
    }
}
