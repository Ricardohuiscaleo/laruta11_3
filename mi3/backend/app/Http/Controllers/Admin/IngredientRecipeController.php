<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recipe\IngredientRecipeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class IngredientRecipeController extends Controller
{
    public function __construct(
        private IngredientRecipeService $service,
    ) {}

    /**
     * List all composite ingredients with sub-recipe summaries.
     * GET /api/v1/admin/ingredient-recipes
     */
    public function index(): JsonResponse
    {
        $data = $this->service->getCompositeIngredients();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a new ingredient and mark it as composite.
     * POST /api/v1/admin/ingredient-recipes
     */
    public function create(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'unit' => 'required|string|in:g,kg,ml,L,unidad',
                'category' => 'nullable|string|max:100',
            ]);

            $ingredient = \App\Models\Ingredient::create([
                'nombre' => $request->input('name'),
                'unidad' => $request->input('unit'),
                'categoria' => $request->input('category', 'Pre-elaborados'),
                'is_composite' => true,
                'costo_unitario' => 0,
                'stock' => 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => ['id' => $ingredient->id],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get sub-recipe detail for a composite ingredient.
     * GET /api/v1/admin/ingredient-recipes/{ingredientId}
     */
    public function show(int $ingredientId): JsonResponse
    {
        try {
            $data = $this->service->getSubRecipe($ingredientId);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ingrediente compuesto no encontrado',
            ], 404);
        }
    }

    /**
     * Create or update a sub-recipe for an ingredient.
     * POST /api/v1/admin/ingredient-recipes/{ingredientId}
     */
    public function store(Request $request, int $ingredientId): JsonResponse
    {
        try {
            $request->validate([
                'children' => 'required|array|min:1',
                'children.*.child_ingredient_id' => 'required|integer|exists:ingredients,id',
                'children.*.quantity' => 'required|numeric|gt:0',
                'children.*.unit' => 'required|string|in:g,kg,ml,L,unidad',
            ]);

            $compositeCost = $this->service->saveSubRecipe(
                $ingredientId,
                $request->input('children')
            );

            return response()->json([
                'success' => true,
                'composite_cost' => $compositeCost,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ingrediente no encontrado',
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
     * Delete a sub-recipe (remove all children, unmark composite).
     * DELETE /api/v1/admin/ingredient-recipes/{ingredientId}
     */
    public function destroy(int $ingredientId): JsonResponse
    {
        try {
            $this->service->deleteSubRecipe($ingredientId);

            return response()->json(['success' => true]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Ingrediente no encontrado',
            ], 404);
        }
    }
}
