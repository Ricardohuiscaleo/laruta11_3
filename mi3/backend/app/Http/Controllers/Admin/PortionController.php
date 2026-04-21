<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PortionStandard;
use App\Services\Recipe\RecipeAIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortionController extends Controller
{
    public function __construct(
        private RecipeAIService $aiService,
    ) {}

    /**
     * GET /api/v1/admin/portions
     */
    public function index(): JsonResponse
    {
        $rows = DB::table('portion_standards')
            ->join('categories', 'categories.id', '=', 'portion_standards.category_id')
            ->join('ingredients', 'ingredients.id', '=', 'portion_standards.ingredient_id')
            ->select(
                'portion_standards.id',
                'portion_standards.category_id',
                'categories.name as category_name',
                'portion_standards.ingredient_id',
                'ingredients.name as ingredient_name',
                'ingredients.unit as ingredient_unit',
                'ingredients.cost_per_unit',
                'portion_standards.quantity',
                'portion_standards.unit',
            )
            ->orderBy('categories.name')
            ->orderBy('ingredients.name')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * GET /api/v1/admin/portions/{categoryId}
     */
    public function show(int $categoryId): JsonResponse
    {
        $rows = DB::table('portion_standards')
            ->join('ingredients', 'ingredients.id', '=', 'portion_standards.ingredient_id')
            ->where('portion_standards.category_id', $categoryId)
            ->select(
                'portion_standards.id',
                'portion_standards.ingredient_id',
                'ingredients.name as ingredient_name',
                'ingredients.unit as ingredient_unit',
                'ingredients.cost_per_unit',
                'portion_standards.quantity',
                'portion_standards.unit',
            )
            ->orderBy('ingredients.name')
            ->get();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * PUT /api/v1/admin/portions/{categoryId}
     */
    public function update(Request $request, int $categoryId): JsonResponse
    {
        $request->validate([
            'portions' => 'required|array|min:1',
            'portions.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'portions.*.quantity' => 'required|numeric|gt:0',
            'portions.*.unit' => 'required|string|in:g,kg,ml,L,unidad',
        ]);

        DB::beginTransaction();
        try {
            PortionStandard::where('category_id', $categoryId)->delete();

            foreach ($request->input('portions') as $p) {
                PortionStandard::create([
                    'category_id' => $categoryId,
                    'ingredient_id' => $p['ingredient_id'],
                    'quantity' => $p['quantity'],
                    'unit' => $p['unit'],
                ]);
            }

            DB::commit();

            return response()->json(['success' => true, 'count' => count($request->input('portions'))]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/admin/portions/suggest-recipe
     */
    public function suggestRecipe(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|max:500',
            'category_id' => 'nullable|integer|exists:categories,id',
        ]);

        $result = $this->aiService->suggestRecipe(
            $request->input('description'),
            $request->input('category_id') ? (int) $request->input('category_id') : null,
        );

        if (isset($result['error'])) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * POST /api/v1/admin/portions/save-variant
     */
    public function saveVariant(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => 'required|integer|exists:categories,id',
            'variant' => 'required|array',
            'variant.name' => 'required|string|max:255',
            'variant.description' => 'nullable|string|max:500',
            'variant.suggested_price' => 'required|integer|min:100',
            'variant.total_cost' => 'nullable|numeric',
            'variant.ingredients' => 'required|array|min:1',
            'variant.ingredients.*.ingredient_id' => 'required|integer|exists:ingredients,id',
            'variant.ingredients.*.quantity' => 'required|numeric|gt:0',
            'variant.ingredients.*.unit' => 'required|string|in:g,kg,ml,L,unidad',
        ]);

        try {
            $result = $this->aiService->saveVariant(
                $request->input('variant'),
                (int) $request->input('category_id'),
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
