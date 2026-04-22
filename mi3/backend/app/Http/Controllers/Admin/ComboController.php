<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Recipe\ComboService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ComboController extends Controller
{
    public function __construct(
        private ComboService $service,
    ) {}

    /**
     * List all active combos with component counts and margins.
     * GET /api/v1/admin/combos
     */
    public function index(): JsonResponse
    {
        $data = $this->service->getComboList();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * Get combo detail: fixed items + selection groups.
     * GET /api/v1/admin/combos/{productId}
     */
    public function show(int $productId): JsonResponse
    {
        try {
            $data = $this->service->getComboDetail($productId);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Combo no encontrado',
            ], 404);
        }
    }

    /**
     * Save combo components (replace all).
     * POST /api/v1/admin/combos/{productId}
     */
    public function store(Request $request, int $productId): JsonResponse
    {
        try {
            $request->validate([
                'components' => 'required|array|min:1',
                'components.*.child_product_id' => 'required|integer|exists:products,id',
                'components.*.quantity' => 'required|integer|min:1',
                'components.*.is_fixed' => 'required|boolean',
                'components.*.selection_group' => 'nullable|string|max:50',
                'components.*.max_selections' => 'nullable|integer|min:1',
                'components.*.price_adjustment' => 'nullable|numeric|min:0',
                'components.*.sort_order' => 'nullable|integer|min:0',
            ]);

            $this->service->saveComboComponents(
                $productId,
                $request->input('components')
            );

            return response()->json(['success' => true]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Combo no encontrado',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al guardar componentes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete all combo components.
     * DELETE /api/v1/admin/combos/{productId}
     */
    public function destroy(int $productId): JsonResponse
    {
        try {
            $deleted = $this->service->deleteComboComponents($productId);

            return response()->json(['success' => true, 'deleted' => $deleted]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al eliminar componentes: ' . $e->getMessage(),
            ], 500);
        }
    }
}
