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
     * List all beverage products (Snacks + Bebidas categories).
     * GET /api/v1/admin/bebidas
     */
    public function index(): JsonResponse
    {
        $data = $this->service->getBeverages();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Create a new beverage product.
     * POST /api/v1/admin/bebidas
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|gt:0',
                'description' => 'nullable|string',
                'cost_price' => 'nullable|numeric|min:0',
                'stock_quantity' => 'nullable|integer|min:0',
                'min_stock_level' => 'nullable|integer|min:0',
                'subcategory_id' => 'nullable|integer',
                'sku' => 'nullable|string|max:50',
            ]);

            $data = $this->service->createBeverageProduct($request->only([
                'name', 'price', 'description', 'cost_price',
                'stock_quantity', 'min_stock_level',
                'subcategory_id', 'sku',
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
     * Get subcategories for beverage categories (Snacks/Bebidas).
     * GET /api/v1/admin/bebidas/subcategorias
     */
    public function subcategories(): JsonResponse
    {
        $data = $this->service->getSubcategories();

        return response()->json(['success' => true, 'data' => $data]);
    }
}
