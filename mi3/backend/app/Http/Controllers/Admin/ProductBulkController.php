<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductBulkController extends Controller
{
    /**
     * Toggle is_active for selected products (flip 1→0, 0→1).
     * PATCH /api/v1/admin/productos/toggle
     */
    public function toggle(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'integer|exists:products,id',
            ]);

            $ids = $request->input('product_ids');

            $toggled = DB::table('products')
                ->whereIn('id', $ids)
                ->update(['is_active' => DB::raw('NOT is_active')]);

            return response()->json([
                'success' => true,
                'toggled' => $toggled,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Bulk price adjustment for selected products.
     * PATCH /api/v1/admin/productos/bulk-price
     */
    public function bulkPrice(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'integer|exists:products,id',
                'adjustment' => 'required|integer',
            ]);

            $ids = $request->input('product_ids');
            $adjustment = (int) $request->input('adjustment');

            // Cast to int above prevents SQL injection; filter rows where result > 0
            $updated = DB::table('products')
                ->whereIn('id', $ids)
                ->whereRaw('price + ? > 0', [$adjustment])
                ->update(['price' => DB::raw('price + ' . $adjustment)]);

            return response()->json([
                'success' => true,
                'updated' => $updated,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Bulk delete selected products (permanent).
     * DELETE /api/v1/admin/productos/bulk-delete
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'integer|exists:products,id',
            ]);

            $ids = $request->input('product_ids');

            // Delete recipe associations first
            DB::table('product_recipes')->whereIn('product_id', $ids)->delete();
            // Delete combo component associations
            DB::table('combo_components')->whereIn('combo_product_id', $ids)->delete();
            DB::table('combo_components')->whereIn('child_product_id', $ids)->delete();
            // Also clean combo_items legacy table if exists
            try {
                DB::table('combo_items')->whereIn('combo_id', $ids)->delete();
            } catch (\Exception $e) {
                // Table may not exist — ignore
            }
            // Delete the products
            $deleted = DB::table('products')->whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'deleted' => $deleted,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
