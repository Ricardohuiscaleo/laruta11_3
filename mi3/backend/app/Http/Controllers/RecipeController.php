<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Recipe\RecipeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RecipeController extends Controller
{
    public function __construct(
        private RecipeService $recipeService,
    ) {}

    /**
     * List all active products with recipe costs and margins.
     * GET /api/v1/admin/recetas
     */
    public function index(Request $request): JsonResponse
    {
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
