<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recipe\BeverageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BeverageController extends Controller
{
    public function __construct(
        private BeverageService $service,
    ) {}

    /**
     * List all beverage ingredients with linked products.
     * GET /api/v1/admin/bebidas
     */
    public function index(): JsonResponse
    {
        $data = $this->service->getBeverages();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a new beverage ingredient.
     * POST /api/v1/admin/bebidas
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'unit' => 'required|string|in:unidad,L,ml',
                'cost_per_unit' => 'required|numeric|gt:0',
                'supplier' => 'nullable|string|max:255',
                'min_stock_level' => 'nullable|numeric|min:0',
            ]);

            $data = $this->service->createBeverageIngredient($request->only([
                'name', 'unit', 'cost_per_unit', 'supplier', 'min_stock_level',
            ]));

            return response()->json(['success' => true, 'data' => $data], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Create a new beverage product linked to an ingredient.
     * POST /api/v1/admin/bebidas/producto
     */
    public function storeProduct(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|gt:0',
                'description' => 'nullable|string|max:2000',
                'ingredient_id' => 'required|integer|exists:ingredients,id',
            ]);

            $data = $this->service->createBeverageProduct($request->only([
                'name', 'price', 'description', 'ingredient_id',
            ]));

            return response()->json(['success' => true, 'data' => $data], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
