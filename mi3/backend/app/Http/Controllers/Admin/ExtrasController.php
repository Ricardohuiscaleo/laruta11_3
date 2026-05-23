<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recipe\ExtrasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExtrasController extends Controller
{
    public function __construct(
        private ExtrasService $service,
    ) {}

    public function index(): JsonResponse
    {
        $data = $this->service->getExtras();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'price' => 'required|numeric|gt:0',
                'description' => 'nullable|string',
                'cost_price' => 'nullable|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'subcategory_id' => 'nullable|integer',
            ]);

            $data = $this->service->createExtra($request->only([
                'name', 'price', 'description', 'cost_price', 'sale_price', 'subcategory_id',
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

    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'price' => 'sometimes|numeric|gt:0',
                'description' => 'nullable|string',
                'cost_price' => 'nullable|numeric|min:0',
                'sale_price' => 'nullable|numeric|min:0',
                'subcategory_id' => 'nullable|integer',
                'is_active' => 'nullable|boolean',
            ]);

            $data = $this->service->updateExtra($id, $request->only([
                'name', 'price', 'description', 'cost_price', 'sale_price',
                'subcategory_id', 'is_active',
            ]));

            return response()->json(['success' => true, 'data' => $data]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
