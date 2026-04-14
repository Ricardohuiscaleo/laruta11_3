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

    /**
     * Update ingredient fields (stock, min, name, category).
     * PATCH /api/v1/admin/stock/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'current_stock' => 'nullable|numeric',
            'min_stock_level' => 'nullable|numeric|min:0',
            'name' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ]);

        $ingredient = \App\Models\Ingredient::findOrFail($id);
        $ingredient->update(array_filter($data, fn($v) => $v !== null));

        return response()->json(['success' => true, 'data' => $ingredient]);
    }

    /**
     * Register consumption (subtract stock).
     * POST /api/v1/admin/stock/consumir
     */
    public function consumir(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:ingredients,id',
            'items.*.cantidad' => 'required|numeric|min:0.01',
            'items.*.notas' => 'nullable|string|max:255',
        ]);

        $results = [];
        foreach ($data['items'] as $item) {
            $ingredient = \App\Models\Ingredient::findOrFail($item['id']);
            $prev = (float) $ingredient->current_stock;
            $new = $prev - $item['cantidad'];
            $ingredient->update(['current_stock' => $new]);

            \Illuminate\Support\Facades\DB::table('inventory_transactions')->insert([
                'transaction_type' => 'adjustment',
                'ingredient_id' => $item['id'],
                'quantity' => -$item['cantidad'],
                'unit' => $ingredient->unit,
                'previous_stock' => $prev,
                'new_stock' => $new,
                'notes' => $item['notas'] ?? 'Consumo manual',
                'created_at' => now(),
            ]);

            $results[] = ['id' => $item['id'], 'name' => $ingredient->name, 'prev' => $prev, 'new' => $new];
        }

        return response()->json(['success' => true, 'applied' => $results]);
    }
}
