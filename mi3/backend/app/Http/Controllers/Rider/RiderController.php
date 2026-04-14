<?php

namespace App\Http\Controllers\Rider;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\DeliveryAssignment;
use App\Services\Delivery\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    public function __construct(
        private LocationService $locationService,
    ) {}

    /**
     * POST /api/v1/rider/location
     * Persiste la posición GPS del rider y emite RiderLocationUpdated.
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $request->validate([
            'latitud'          => 'required|numeric|between:-90,90',
            'longitud'         => 'required|numeric|between:-180,180',
            'precision_metros' => 'nullable|integer|min:0',
            'velocidad_kmh'    => 'nullable|numeric|min:0',
            'heading'          => 'nullable|numeric|between:0,360',
        ]);

        $personal = $request->get('personal');

        $location = $this->locationService->updateRiderLocation(
            riderId: $personal->id,
            lat: (float) $request->input('latitud'),
            lng: (float) $request->input('longitud'),
            precision: (int) $request->input('precision_metros', 0),
            speed: $request->filled('velocidad_kmh') ? (float) $request->input('velocidad_kmh') : null,
            heading: $request->filled('heading') ? (float) $request->input('heading') : null,
        );

        return response()->json([
            'success'  => true,
            'location' => $location,
        ]);
    }

    /**
     * GET /api/v1/rider/current-assignment
     * Retorna la DeliveryAssignment activa del rider con datos del pedido.
     */
    public function currentAssignment(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $assignment = DeliveryAssignment::with('order')
            ->where('rider_id', $personal->id)
            ->whereIn('status', ['assigned', 'picked_up'])
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            return response()->json([
                'success'    => true,
                'assignment' => null,
            ]);
        }

        return response()->json([
            'success'    => true,
            'assignment' => $assignment,
        ]);
    }

    /**
     * PATCH /api/v1/rider/current-assignment/status
     * Actualiza el estado de la asignación activa del rider.
     * - 'picked_up': actualiza picked_up_at, cambia order_status a 'out_for_delivery'
     * - 'delivered': actualiza delivered_at, cambia order_status a 'delivered'
     * Emite OrderStatusUpdated en ambos casos.
     */
    public function updateAssignmentStatus(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'required|string|in:picked_up,delivered',
        ]);

        $personal = $request->get('personal');
        $newStatus = $request->input('status');

        $assignment = DeliveryAssignment::with('order')
            ->where('rider_id', $personal->id)
            ->whereIn('status', ['assigned', 'picked_up'])
            ->latest('assigned_at')
            ->first();

        if (!$assignment) {
            return response()->json([
                'success' => false,
                'error'   => 'No tienes una asignación activa',
            ], 404);
        }

        $order = $assignment->order;

        if ($newStatus === 'picked_up') {
            $assignment->update([
                'status'       => 'picked_up',
                'picked_up_at' => now(),
            ]);

            DB::table('tuu_orders')
                ->where('id', $assignment->order_id)
                ->update(['order_status' => 'out_for_delivery']);

            $orderStatus = 'out_for_delivery';
        } else {
            // delivered
            $assignment->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            DB::table('tuu_orders')
                ->where('id', $assignment->order_id)
                ->update(['order_status' => 'delivered']);

            $orderStatus = 'delivered';
        }

        broadcast(new OrderStatusUpdated(
            orderId: $assignment->order_id,
            orderNumber: $order->order_number,
            orderStatus: $orderStatus,
            riderId: $personal->id,
            estimatedDeliveryTime: null,
            updatedAt: now()->toISOString(),
        ));

        return response()->json([
            'success'    => true,
            'assignment' => $assignment->fresh(),
            'status'     => $orderStatus,
        ]);
    }
}
