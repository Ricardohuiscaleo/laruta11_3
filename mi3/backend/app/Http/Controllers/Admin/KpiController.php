<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rendicion;
use App\Services\Compra\CompraService;
use App\Services\Compra\SugerenciaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    public function __construct(
        private CompraService $compraService,
        private SugerenciaService $sugerenciaService,
    ) {}

    /**
     * KPIs financieros (saldo disponible).
     * GET /api/v1/admin/kpis
     */
    public function index(): JsonResponse
    {
        $data = $this->compraService->getSaldoDisponible();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Historial de saldo / capital de trabajo.
     * GET /api/v1/admin/kpis/historial-saldo
     */
    public function historialSaldo(): JsonResponse
    {
        $historial = $this->compraService->getHistorialSaldo();

        return response()->json(['success' => true, 'historial' => $historial]);
    }

    /**
     * Proyección de compras (placeholder).
     * GET /api/v1/admin/kpis/proyeccion
     */
    public function proyeccion(): JsonResponse
    {
        return response()->json(['success' => true, 'proyeccion' => []]);
    }

    /**
     * Historial de rendiciones (saldo encadenado).
     * GET /api/v1/admin/kpis/rendiciones
     */
    public function rendiciones(): JsonResponse
    {
        $rendiciones = Rendicion::orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'token', 'saldo_anterior', 'total_compras', 'saldo_resultante', 'monto_transferido', 'saldo_nuevo', 'estado', 'created_at', 'aprobado_at']);

        return response()->json(['success' => true, 'rendiciones' => $rendiciones]);
    }

    /**
     * Precio histórico de un ítem.
     * GET /api/v1/admin/kpis/precio-historico/{id}
     */
    public function precioHistorico(int $id, Request $request): JsonResponse
    {
        $itemType = $request->query('type', 'ingredient');

        $data = $this->sugerenciaService->precioHistorico($id, $itemType);

        if (!$data) {
            return response()->json([
                'success' => true,
                'data'    => null,
                'message' => 'No hay historial de precios para este ítem',
            ]);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
