<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Ventas\VentasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentasController extends Controller
{
    public function __construct(
        private readonly VentasService $ventasService,
    ) {}

    /**
     * GET /api/v1/admin/ventas
     * Paginated transactions list.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period'   => 'sometimes|string|in:shift_today,today,week,month',
            'search'   => 'sometimes|nullable|string|max:100',
            'page'     => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $period  = $request->query('period', 'shift_today');
        $search  = $request->query('search');
        $page    = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', 50);

        $data = $this->ventasService->getTransactions($period, $search, $page, $perPage);

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/v1/admin/ventas/{orderNumber}/detail
     * Full order detail: items, ingredient consumption, totals.
     */
    public function detail(string $orderNumber): JsonResponse
    {
        $data = $this->ventasService->getOrderDetail($orderNumber);

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Orden no encontrada',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * GET /api/v1/admin/ventas/kpis
     * Aggregated KPIs + payment breakdown.
     */
    public function kpis(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:shift_today,today,week,month',
        ]);

        $period = $request->query('period', 'shift_today');

        $kpis = $this->ventasService->getKpis($period);
        $breakdown = $this->ventasService->getPaymentBreakdown($period);

        return response()->json([
            'success' => true,
            'data'    => [
                'kpis'              => $kpis,
                'payment_breakdown' => $breakdown,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/ventas/top-products
     * Top products by quantity or profit.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:shift_today,today,week,month',
            'limit'  => 'sometimes|integer|min:1|max:50',
            'sort'   => 'sometimes|string|in:quantity,profit',
        ]);

        $period = $request->query('period', 'month');
        $limit  = (int) $request->query('limit', 10);
        $sort   = $request->query('sort', 'quantity');

        return response()->json([
            'success' => true,
            'data'    => $this->ventasService->getTopProducts($period, $limit, $sort),
        ]);
    }

    /**
     * GET /api/v1/admin/ventas/cmv
     * CMV breakdown by ingredient.
     */
    public function cmv(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:shift_today,today,week,month',
            'month'  => 'sometimes|nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $period = $request->query('period', 'month');
        $month  = $request->query('month');

        return response()->json([
            'success' => true,
            'data'    => $this->ventasService->getCmvBreakdown($period, $month),
        ]);
    }

    /**
     * GET /api/v1/admin/ventas/cmv/{ingredientId}/products
     * Breakdown of which products consumed a specific ingredient.
     */
    public function cmvIngredientProducts(Request $request, int $ingredientId): JsonResponse
    {
        $request->validate([
            'period' => 'sometimes|string|in:shift_today,today,week,month',
            'month'  => 'sometimes|nullable|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $period = $request->query('period', 'month');
        $month  = $request->query('month');

        return response()->json([
            'success' => true,
            'data'    => $this->ventasService->getCmvIngredientProducts($ingredientId, $period, $month),
        ]);
    }

    /**
     * GET /api/v1/admin/ventas/monthly
     * Monthly aggregates for charts.
     */
    public function monthly(Request $request): JsonResponse
    {
        $request->validate([
            'months' => 'sometimes|integer|min:1|max:24',
        ]);

        $months = (int) $request->query('months', 6);

        return response()->json([
            'success' => true,
            'data'    => $this->ventasService->getMonthlyAggregates($months),
        ]);
    }
}
