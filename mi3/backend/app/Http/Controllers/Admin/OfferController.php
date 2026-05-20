<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfferController extends Controller
{
    /**
     * List all active products grouped by category with offer status.
     * GET /api/v1/admin/offers
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search');

        $query = DB::table('products')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.is_active', 1)
            ->select(
                'products.id',
                'products.name',
                'products.price',
                'products.sale_price',
                'products.is_featured',
                'categories.id as category_id',
                'categories.name as category_name'
            )
            ->orderBy('categories.sort_order')
            ->orderBy('categories.name')
            ->orderBy('products.name');

        if ($search) {
            $query->where('products.name', 'like', "%{$search}%");
        }

        $products = $query->get();

        $grouped = [];
        foreach ($products as $p) {
            $key = $p->category_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'category_id' => $p->category_id,
                    'category_name' => $p->category_name,
                    'products' => [],
                ];
            }
            $grouped[$key]['products'][] = [
                'id' => $p->id,
                'name' => $p->name,
                'price' => (float) $p->price,
                'sale_price' => $p->sale_price ? (float) $p->sale_price : null,
                'is_featured' => (bool) $p->is_featured,
                'discount_percent' => $p->sale_price && $p->price > 0
                    ? round((1 - $p->sale_price / $p->price) * 100)
                    : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => array_values($grouped),
        ]);
    }

    /**
     * Apply offer (is_featured + sale_price) to one or more products.
     * POST /api/v1/admin/offers/apply
     */
    public function apply(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|integer|exists:products,id',
                'discount_percent' => 'required|numeric|min:1|max:99',
                'round_to' => 'nullable|integer|min:1',
            ]);

            $productIds = $request->input('product_ids');
            $discountPercent = (float) $request->input('discount_percent');
            $roundTo = $request->input('round_to', 10);

            $products = DB::table('products')
                ->whereIn('id', $productIds)
                ->where('is_active', 1)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se encontraron productos activos',
                ], 404);
            }

            $updated = [];
            foreach ($products as $product) {
                $salePrice = floor($product->price * (1 - $discountPercent / 100) / $roundTo) * $roundTo;
                if ($salePrice < 0) $salePrice = 0;

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'is_featured' => 1,
                        'sale_price' => $salePrice,
                        'updated_at' => now(),
                    ]);

                $updated[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'sale_price' => (float) $salePrice,
                    'discount' => (float) $product->price - $salePrice,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Oferta aplicada a ' . count($updated) . ' productos',
                'data' => $updated,
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
     * Remove offer from one or more products.
     * POST /api/v1/admin/offers/remove
     */
    public function remove(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'product_ids' => 'required|array|min:1',
                'product_ids.*' => 'required|integer|exists:products,id',
            ]);

            $productIds = $request->input('product_ids');

            $affected = DB::table('products')
                ->whereIn('id', $productIds)
                ->where('is_active', 1)
                ->update([
                    'is_featured' => 0,
                    'sale_price' => null,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Oferta removida de $affected productos",
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
     * Apply offer to all active products in a category.
     * POST /api/v1/admin/offers/apply-category
     */
    public function applyCategory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category_id' => 'required|integer|exists:categories,id',
                'discount_percent' => 'required|numeric|min:1|max:99',
                'round_to' => 'nullable|integer|min:1',
            ]);

            $categoryId = (int) $request->input('category_id');
            $discountPercent = (float) $request->input('discount_percent');
            $roundTo = $request->input('round_to', 10);

            $products = DB::table('products')
                ->where('category_id', $categoryId)
                ->where('is_active', 1)
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'error' => 'No hay productos activos en esta categoría',
                ], 404);
            }

            $updated = [];
            foreach ($products as $product) {
                $salePrice = floor($product->price * (1 - $discountPercent / 100) / $roundTo) * $roundTo;
                if ($salePrice < 0) $salePrice = 0;

                DB::table('products')
                    ->where('id', $product->id)
                    ->update([
                        'is_featured' => 1,
                        'sale_price' => $salePrice,
                        'updated_at' => now(),
                    ]);

                $updated[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'price' => (float) $product->price,
                    'sale_price' => (float) $salePrice,
                ];
            }

            $category = DB::table('categories')->find($categoryId);

            return response()->json([
                'success' => true,
                'message' => 'Oferta aplicada a toda la categoría ' . ($category->name ?? ''),
                'data' => $updated,
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
     * Remove offers from all products in a category.
     * POST /api/v1/admin/offers/remove-category
     */
    public function removeCategory(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'category_id' => 'required|integer|exists:categories,id',
            ]);

            $categoryId = (int) $request->input('category_id');

            $affected = DB::table('products')
                ->where('category_id', $categoryId)
                ->where('is_active', 1)
                ->update([
                    'is_featured' => 0,
                    'sale_price' => null,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => "Ofertas removidas de $affected productos en la categoría",
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
