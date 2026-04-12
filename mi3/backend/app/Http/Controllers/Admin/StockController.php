<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compra\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function __construct(
        private StockService $stockService,
    ) {}

    /**
     * Inventario con semáforo.
     * GET /api/v1/admin/stock
     */
    public function index(Request $request): JsonResponse
    {
        $tipo = $request->query('tipo', 'ingredientes');

        $items = $this->stockService->getInventario($tipo);

        return response()->json(['success' => true, 'items' => $items]);
    }

    /**
     * Reporte de bebidas en markdown.
     * GET /api/v1/admin/stock/bebidas
     */
    public function bebidas(): JsonResponse
    {
        $markdown = $this->stockService->reporteBebidas();

        return response()->json(['success' => true, 'markdown' => $markdown]);
    }

    /**
     * Ajuste masivo de stock vía markdown.
     * POST /api/v1/admin/stock/ajuste-masivo
     */
    public function ajusteMasivo(Request $request): JsonResponse
    {
        $request->validate([
            'texto' => 'required|string',
        ]);

        try {
            $parsed = $this->stockService->parsearMarkdown($request->input('texto'));

            if (empty($parsed['valid'])) {
                return response()->json([
                    'success' => false,
                    'error'   => 'No se encontraron ítems válidos para ajustar',
                    'errors'  => $parsed['errors'],
                ]);
            }

            $result = $this->stockService->aplicarAjuste($parsed['valid']);

            return response()->json([
                'success' => true,
                'applied' => $result['applied'],
                'errors'  => $parsed['errors'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error'   => 'Error al aplicar ajuste: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Previsualización de ajuste masivo.
     * POST /api/v1/admin/stock/preview-ajuste
     */
    public function previewAjuste(Request $request): JsonResponse
    {
        $request->validate([
            'texto' => 'required|string',
        ]);

        $parsed = $this->stockService->parsearMarkdown($request->input('texto'));

        return response()->json([
            'success' => true,
            'valid'   => $parsed['valid'],
            'errors'  => $parsed['errors'],
        ]);
    }
}
