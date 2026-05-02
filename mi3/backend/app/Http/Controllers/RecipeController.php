<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Recipe\RecipeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipeService,
    ) {}

    /**
     * Create a new product (food item).
     * POST /api/v1/admin/recetas/crear-producto
     */
    public function createProduct(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|gt:0',
                'description' => 'nullable|string',
                'cost_price' => 'nullable|numeric|min:0',
                'category_id' => 'required|integer|exists:categories,id',
                'subcategory_id' => 'nullable|integer',
                'stock_quantity' => 'nullable|integer|min:0',
                'min_stock_level' => 'nullable|integer|min:0',
                'sku' => 'nullable|string|max:50',
            ]);

            $id = DB::table('products')->insertGetId([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'cost_price' => $request->cost_price ?? 0,
                'category_id' => $request->category_id,
                'subcategory_id' => $request->subcategory_id,
                'stock_quantity' => $request->stock_quantity ?? 0,
                'min_stock_level' => $request->min_stock_level ?? 5,
                'sku' => $request->sku,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $product = DB::table('products')->find($id);

            return response()->json(['success' => true, 'data' => (array) $product], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * List all active products with recipe costs and margins.
     * GET /api/v1/admin/recetas
     */
    public function index(Request $request): JsonResponse
    {
        // Grouped mode: return products organized by category
        if ($request->query('grouped') === 'true') {
            $search = $request->query('search');
            $data = $this->recipeService->getRecipesGroupedByCategory($search);

            return response()->json(['success' => true, 'data' => $data]);
        }

        // Default flat mode (backward compatible)
        $categoryId = $request->query('category_id') ? (int) $request->query('category_id') : null;
        $search = $request->query('search');
        $sortBy = $request->query('sort_by');

        $recipes = $this->recipeService->getRecipesWithCosts($categoryId, $search, $sortBy);

        return response()->json(['success' => true, 'data' => $recipes]);
    }

    /**
     * Get full recipe detail for a product.
     * GET /api/v1/admin/recetas/{productId}
     */
    public function show(int $productId): JsonResponse
    {
        try {
            $detail = $this->recipeService->getRecipeDetail($productId);

            return response()->json(['success' => true, 'data' => $detail]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        }
    }

    /**
     * Create a recipe for a product.
     * POST /api/v1/admin/recetas/{productId}
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        try {
            $request->validate([
                'ingredients' => 'required|array|min:1',
                'ingredients.*.ingredient_id' => 'required|integer|exists:ingredients,id',
                'ingredients.*.quantity' => 'required|numeric|gt:0',
                'ingredients.*.unit' => 'required|string|in:g,kg,ml,L,unidad',
            ]);

            $costPrice = $this->recipeService->createRecipe(
                $productId,
                $request->input('ingredients')
            );

            return response()->json([
                'success' => true,
                'cost_price' => $costPrice,
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Update (replace) a product's recipe.
     * PUT /api/v1/admin/recetas/{productId}
     */
    public function update(Request $request, int $productId): JsonResponse
    {
        try {
            $request->validate([
                'ingredients' => 'required|array|min:1',
                'ingredients.*.ingredient_id' => 'required|integer|exists:ingredients,id',
                'ingredients.*.quantity' => 'required|numeric|gt:0',
                'ingredients.*.unit' => 'required|string|in:g,kg,ml,L,unidad',
            ]);

            $costPrice = $this->recipeService->updateRecipe(
                $productId,
                $request->input('ingredients')
            );

            return response()->json([
                'success' => true,
                'cost_price' => $costPrice,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Remove a single ingredient from a product's recipe.
     * DELETE /api/v1/admin/recetas/{productId}/{ingredientId}
     */
    public function destroyIngredient(int $productId, int $ingredientId): JsonResponse
    {
        try {
            $costPrice = $this->recipeService->removeIngredient($productId, $ingredientId);

            return response()->json([
                'success' => true,
                'cost_price' => $costPrice,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Producto o ingrediente no encontrado',
            ], 404);
        }
    }

    /**
     * Preview a bulk ingredient cost adjustment.
     * POST /api/v1/admin/recetas/bulk-adjustment/preview
     */
    public function bulkPreview(Request $request): JsonResponse
    {
        $request->validate([
            'scope' => 'required|string',
            'type' => 'required|string|in:percentage,fixed',
            'value' => 'required|numeric',
        ]);

        $preview = $this->recipeService->bulkAdjustmentPreview(
            $request->input('scope'),
            $request->input('type'),
            (float) $request->input('value')
        );

        return response()->json(['success' => true, 'data' => $preview]);
    }

    /**
     * Apply a bulk ingredient cost adjustment.
     * POST /api/v1/admin/recetas/bulk-adjustment
     */
    public function bulkApply(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'scope' => 'required|string',
                'type' => 'required|string|in:percentage,fixed',
                'value' => 'required|numeric',
            ]);

            $result = $this->recipeService->bulkAdjustmentApply(
                $request->input('scope'),
                $request->input('type'),
                (float) $request->input('value')
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Preview replacing one ingredient with another across all recipes.
     * POST /api/v1/admin/recetas/replace-ingredient/preview
     */
    public function replacePreview(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source_id' => 'required|integer|exists:ingredients,id',
                'target_id' => 'required|integer|exists:ingredients,id|different:source_id',
            ]);

            $preview = $this->recipeService->replaceIngredientPreview(
                (int) $request->input('source_id'),
                (int) $request->input('target_id')
            );

            return response()->json(['success' => true, 'data' => $preview]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Apply replacing one ingredient with another across all recipes.
     * POST /api/v1/admin/recetas/replace-ingredient
     */
    public function replaceApply(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'source_id' => 'required|integer|exists:ingredients,id',
                'target_id' => 'required|integer|exists:ingredients,id|different:source_id',
            ]);

            $result = $this->recipeService->replaceIngredientApply(
                (int) $request->input('source_id'),
                (int) $request->input('target_id')
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ingrediente no encontrado',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al reemplazar ingrediente: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get categories and subcategories for product selectors.
     * GET /api/v1/admin/recetas/catalogo
     */
    public function catalogo(): JsonResponse
    {
        $catalog = $this->recipeService->getCatalog();

        return response()->json(['success' => true, 'data' => $catalog]);
    }

    /**
     * Update product metadata (name, description, image, category, subcategory).
     * PUT /api/v1/admin/recetas/{productId}/producto
     */
    public function updateProduct(Request $request, int $productId): JsonResponse
    {
        try {
            $request->validate([
                'name'           => 'sometimes|string|max:255',
                'description'    => 'nullable|string|max:2000',
                'price'          => 'nullable|numeric|gt:0',
                'image_url'      => 'nullable|string|max:500',
                'category_id'    => 'nullable|integer',
                'subcategory_id' => 'nullable|integer',
            ]);

            $result = $this->recipeService->updateProduct($productId, $request->all());

            return response()->json(['success' => true, 'data' => $result]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Producto no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Upload product image.
     * POST /api/v1/admin/recetas/{productId}/imagen
     *
     * Uses direct S3 PUT with SigV4 + public-read ACL (Flysystem doesn't
     * reliably set public ACL on this bucket).
     */
    public function uploadProductImage(Request $request, int $productId): JsonResponse
    {
        try {
            $request->validate([
                'image' => 'required|image|max:5120',
            ]);

            $product = \App\Models\Product::findOrFail($productId);

            $file = $request->file('image');
            $ext = $file->getClientOriginalExtension() ?: 'jpg';
            $key = "products/producto_{$productId}_" . time() . ".{$ext}";

            // Read credentials from config (same source as ImagenService)
            $bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
            $region = config('filesystems.disks.s3.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
            $awsKey = config('filesystems.disks.s3.key', env('AWS_ACCESS_KEY_ID', ''));
            $awsSecret = config('filesystems.disks.s3.secret', env('AWS_SECRET_ACCESS_KEY', ''));

            $body = file_get_contents($file->getRealPath());
            $contentType = $file->getMimeType() ?: 'image/jpeg';

            // SigV4 signed PUT with public-read ACL
            $host = "{$bucket}.s3.{$region}.amazonaws.com";
            $url = "https://{$host}/{$key}";
            $now = gmdate('Ymd\THis\Z');
            $date = gmdate('Ymd');
            $payloadHash = hash('sha256', $body);

            $headers = [
                'content-type' => $contentType,
                'host' => $host,
                'x-amz-acl' => 'public-read',
                'x-amz-content-sha256' => $payloadHash,
                'x-amz-date' => $now,
            ];

            $signedHeaders = implode(';', array_keys($headers));
            $canonicalHeaders = '';
            foreach ($headers as $k => $v) {
                $canonicalHeaders .= "{$k}:{$v}\n";
            }

            $canonicalRequest = "PUT\n/{$key}\n\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
            $credentialScope = "{$date}/{$region}/s3/aws4_request";
            $stringToSign = "AWS4-HMAC-SHA256\n{$now}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

            $kDate = hash_hmac('sha256', $date, "AWS4{$awsSecret}", true);
            $kRegion = hash_hmac('sha256', $region, $kDate, true);
            $kService = hash_hmac('sha256', 's3', $kRegion, true);
            $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
            $signature = hash_hmac('sha256', $stringToSign, $kSigning);

            $auth = "AWS4-HMAC-SHA256 Credential={$awsKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => 'PUT',
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    "Content-Type: {$contentType}",
                    "X-Amz-Acl: public-read",
                    "X-Amz-Date: {$now}",
                    "X-Amz-Content-Sha256: {$payloadHash}",
                    "Authorization: {$auth}",
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) {
                \Illuminate\Support\Facades\Log::error("[uploadProductImage] S3 PUT failed: HTTP {$code} for {$key}. Response: " . substr($resp, 0, 500));
                return response()->json([
                    'success' => false,
                    'error' => "Error subiendo a S3: HTTP {$code}",
                ], 500);
            }

            $publicUrl = "https://{$bucket}.s3.amazonaws.com/{$key}";
            $product->image_url = $publicUrl;
            $product->save();

            return response()->json(['success' => true, 'image_url' => $publicUrl]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['success' => false, 'error' => 'Producto no encontrado'], 404);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("[uploadProductImage] Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error al subir imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get price recommendations based on recipe cost and target margin.
     * GET /api/v1/admin/recetas/recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $targetMargin = $request->query('target_margin')
            ? (float) $request->query('target_margin')
            : 65.0;

        $recommendations = $this->recipeService->getRecommendations($targetMargin);

        return response()->json(['success' => true, 'data' => $recommendations]);
    }

    /**
     * Get stock audit data.
     * GET /api/v1/admin/recetas/audit
     */
    public function audit(): JsonResponse
    {
        $audit = $this->recipeService->getStockAudit();

        return response()->json(['success' => true, 'data' => $audit]);
    }

    /**
     * Export stock audit as CSV download.
     * GET /api/v1/admin/recetas/audit/export
     */
    public function auditExport(): \Symfony\Component\HttpFoundation\Response
    {
        $csv = $this->recipeService->exportAudit('csv');

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="auditoria-stock.csv"',
        ]);
    }
}
