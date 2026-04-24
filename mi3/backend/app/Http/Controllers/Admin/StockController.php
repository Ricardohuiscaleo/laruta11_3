<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IngredientCategory;
use App\Http\Controllers\Controller;
use App\Services\Compra\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'category' => ['nullable', 'string', Rule::in(IngredientCategory::VALID_CATEGORIES)],
            'is_active' => 'nullable|boolean',
        ]);

        $ingredient = \App\Models\Ingredient::findOrFail($id);
        $ingredient->update(array_filter($data, fn($v) => $v !== null));

        broadcast(new \App\Events\StockActualizado('edicion', $id));

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
            $cantidad = (float) $item['cantidad'];

            // Validate stock >= cantidad (Req 2.5)
            if ($prev < $cantidad) {
                return response()->json([
                    'success' => false,
                    'error' => "Stock insuficiente: disponible {$prev} {$ingredient->unit}",
                    'ingredient_id' => $item['id'],
                    'ingredient_name' => $ingredient->name,
                ], 422);
            }

            $new = $prev - $cantidad;
            $cost = $cantidad * (float) $ingredient->cost_per_unit;
            $ingredient->update(['current_stock' => $new]);

            \Illuminate\Support\Facades\DB::table('inventory_transactions')->insert([
                'transaction_type' => 'consumption',
                'ingredient_id' => $item['id'],
                'quantity' => -$cantidad,
                'unit' => $ingredient->unit,
                'previous_stock' => $prev,
                'new_stock' => $new,
                'notes' => ($item['notas'] ?? 'Consumo manual') . " | costo: $" . number_format($cost, 0, ',', '.'),
                'created_at' => now(),
            ]);

            $results[] = [
                'id' => $item['id'],
                'name' => $ingredient->name,
                'prev' => $prev,
                'new' => $new,
                'cost' => $cost,
            ];
        }

        broadcast(new \App\Events\StockActualizado('consumo'));

        return response()->json(['success' => true, 'applied' => $results]);
    }

    /**
     * Auditoría física de inventario — ajuste masivo por conteo.
     * POST /api/v1/admin/stock/auditoria
     */
    public function auditoria(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ingredient_id' => 'required|integer',
            'items.*.physical_count' => 'required|numeric|min:0',
        ]);

        $warnings = [];
        $modified = [];
        $valorAntes = 0.0;
        $valorDespues = 0.0;

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            foreach ($data['items'] as $item) {
                $ingredient = \App\Models\Ingredient::find($item['ingredient_id']);
                if (!$ingredient) {
                    $warnings[] = "ingredient_id {$item['ingredient_id']} no encontrado — ignorado";
                    continue;
                }

                $prevStock = (float) $ingredient->current_stock;
                $newStock = (float) $item['physical_count'];
                $costUnit = (float) $ingredient->cost_per_unit;

                $valorAntes += $prevStock * $costUnit;
                $valorDespues += $newStock * $costUnit;

                if (abs($prevStock - $newStock) < 0.001) {
                    continue; // Sin diferencia
                }

                $ingredient->update(['current_stock' => $newStock]);

                \Illuminate\Support\Facades\DB::table('inventory_transactions')->insert([
                    'transaction_type' => 'adjustment',
                    'ingredient_id' => $ingredient->id,
                    'quantity' => $newStock - $prevStock,
                    'unit' => $ingredient->unit,
                    'previous_stock' => $prevStock,
                    'new_stock' => $newStock,
                    'notes' => 'Auditoría física',
                    'created_at' => now(),
                ]);

                $modified[] = [
                    'id' => $ingredient->id,
                    'name' => $ingredient->name,
                    'prev' => $prevStock,
                    'new' => $newStock,
                    'diff' => $newStock - $prevStock,
                ];
            }

            // Recalcular stock_quantity de productos con receta que usen ingredientes ajustados
            $adjustedIds = collect($modified)->pluck('id')->toArray();
            if (!empty($adjustedIds)) {
                $productIds = \Illuminate\Support\Facades\DB::table('product_recipes')
                    ->whereIn('ingredient_id', $adjustedIds)
                    ->distinct()
                    ->pluck('product_id');

                foreach ($productIds as $productId) {
                    \Illuminate\Support\Facades\DB::statement("
                        UPDATE products p
                        SET stock_quantity = (
                            SELECT COALESCE(FLOOR(MIN(
                                CASE WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity
                                     ELSE i.current_stock / pr.quantity END
                            )), 0)
                            FROM product_recipes pr
                            JOIN ingredients i ON pr.ingredient_id = i.id
                            WHERE pr.product_id = p.id AND i.is_active = 1 AND i.current_stock > 0
                        )
                        WHERE p.id = ?
                    ", [$productId]);
                }
            }

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return response()->json([
                'success' => false,
                'error' => 'Error al aplicar auditoría: ' . $e->getMessage(),
            ], 500);
        }

        broadcast(new \App\Events\StockActualizado('auditoria'));

        return response()->json([
            'success' => true,
            'summary' => [
                'items_modified' => count($modified),
                'valor_antes' => $valorAntes,
                'valor_despues' => $valorDespues,
                'diferencia' => $valorDespues - $valorAntes,
            ],
            'modified' => $modified,
            'warnings' => $warnings,
        ]);
    }

    /**
     * Lista de consumibles (Gas, Limpieza, Servicios).
     * GET /api/v1/admin/stock/consumibles
     */
    public function consumibles(): JsonResponse
    {
        $items = \App\Models\Ingredient::where('is_active', 1)
            ->whereIn('category', ['Gas', 'Limpieza', 'Servicios'])
            ->orderBy('category')
            ->orderBy('name')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'category' => $i->category,
                'current_stock' => (float) $i->current_stock,
                'unit' => $i->unit,
                'cost_per_unit' => (float) $i->cost_per_unit,
                'valor_inventario' => (float) $i->current_stock * (float) $i->cost_per_unit,
            ]);

        return response()->json(['success' => true, 'items' => $items]);
    }
}
