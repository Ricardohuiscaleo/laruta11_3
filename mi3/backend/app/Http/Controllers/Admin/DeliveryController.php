<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService,
    ) {}

    /**
     * GET /api/v1/admin/delivery/orders
     * Retorna pedidos activos de delivery con info del rider y última posición GPS.
     */
    public function index(): JsonResponse
    {
        $orders = $this->deliveryService->getActiveOrders();

        return response()->json([
            'success' => true,
            'orders'  => $orders,
        ]);
    }

    /**
     * PATCH /api/v1/admin/delivery/orders/{id}/status
     * Actualiza el estado de un pedido. Retorna 422 si el estado es inválido.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|string',
        ]);

        try {
            $order = $this->deliveryService->updateOrderStatus($id, $request->input('status'));

            return response()->json([
                'success' => true,
                'order'   => $order,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/admin/delivery/orders/{id}/assign-rider
     * Asigna un rider a un pedido.
     */
    public function assignRider(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'rider_id' => 'required|integer',
        ]);

        $personal = $request->get('personal');

        $assignment = $this->deliveryService->assignRider(
            orderId: $id,
            riderId: (int) $request->input('rider_id'),
            assignedBy: $personal->id,
        );

        return response()->json([
            'success'    => true,
            'assignment' => $assignment,
        ]);
    }

    /**
     * GET /api/v1/admin/delivery/riders
     * Retorna riders disponibles con última posición GPS.
     */
    public function riders(): JsonResponse
    {
        $riders = $this->deliveryService->getAvailableRiders();

        return response()->json([
            'success' => true,
            'riders'  => $riders,
        ]);
    }

    /**
     * POST /api/v1/admin/delivery/simulate
     * Triggers the delivery simulation in the background.
     */
    public function simulate(): JsonResponse
    {
        // Run artisan in background using popen (non-blocking, works in Docker)
        $cmd = 'php ' . base_path('artisan') . ' delivery:simulate --steps=20 > /dev/null 2>&1 &';
        pclose(popen($cmd, 'r'));

        return response()->json([
            'success' => true,
            'message' => 'Simulación iniciada (20 pasos, ~60s)',
        ]);
    }
}
