<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicRiderController extends Controller
{
    public function __construct(
        private DeliveryService $deliveryService,
        private LocationService $locationService,
    ) {}

    /**
     * GET /api/v1/public/rider-orders/{orderId}
     * Retorna datos completos del pedido para la página pública del rider.
     */
    public function show(int $orderId): JsonResponse
    {
        $order = DB::table('tuu_orders')
            ->where('id', $orderId)
            ->first();

        if (!$order || $order->delivery_type !== 'delivery') {
            return response()->json([
                'success' => false,
                'error'   => 'Pedido no encontrado',
            ], 404);
        }

        // Items del pedido
        $items = DB::table('tuu_order_items')
            ->where('order_id', $orderId)
            ->select(['product_name', 'quantity', 'product_price'])
            ->get();

        // Ubicación del food truck activo
        $foodTruck = DB::table('food_trucks')
            ->where('activo', 1)
            ->orderBy('id', 'asc')
            ->select(['latitud', 'longitud'])
            ->first();

        return response()->json([
            'success' => true,
            'order'   => [
                'id'                    => $order->id,
                'order_number'          => $order->order_number,
                'order_status'          => $order->order_status,
                'customer_name'         => $order->customer_name,
                'customer_phone'        => $order->customer_phone,
                'delivery_address'      => $order->delivery_address,
                'delivery_fee'          => $order->delivery_fee,
                'card_surcharge'        => $order->card_surcharge,
                'subtotal'              => $order->subtotal,
                'product_price'         => $order->product_price,
                'payment_method'        => $order->payment_method,
                'delivery_distance_km'  => $order->delivery_distance_km,
                'delivery_duration_min' => $order->delivery_duration_min,
                'rider_last_lat'        => $order->rider_last_lat,
                'rider_last_lng'        => $order->rider_last_lng,
                'items'                 => $items,
                'food_truck'            => $foodTruck
                    ? ['latitud' => $foodTruck->latitud, 'longitud' => $foodTruck->longitud]
                    : null,
            ],
        ]);
    }

    /**
     * PATCH /api/v1/public/rider-orders/{orderId}/status
     * Actualiza el estado del pedido (out_for_delivery o delivered).
     */
    public function updateStatus(Request $request, int $orderId): JsonResponse
    {
        $status = $request->input('status');

        if (!in_array($status, ['out_for_delivery', 'delivered'], true)) {
            return response()->json([
                'success' => false,
                'error'   => 'Status inválido. Valores permitidos: out_for_delivery, delivered',
            ], 422);
        }

        $order = DB::table('tuu_orders')
            ->where('id', $orderId)
            ->first();

        if (!$order || $order->delivery_type !== 'delivery') {
            return response()->json([
                'success' => false,
                'error'   => 'Pedido no encontrado',
            ], 404);
        }

        $updatedOrder = $this->deliveryService->updateOrderStatus($orderId, $status);

        return response()->json([
            'success' => true,
            'order'   => $updatedOrder,
        ]);
    }

    /**
     * POST /api/v1/public/rider-orders/{orderId}/location
     * Recibe coordenadas GPS del rider y actualiza la posición.
     */
    public function updateLocation(Request $request, int $orderId): JsonResponse
    {
        $latitud  = $request->input('latitud');
        $longitud = $request->input('longitud');

        if (!is_numeric($latitud) || !is_numeric($longitud)
            || $latitud < -90 || $latitud > 90
            || $longitud < -180 || $longitud > 180
        ) {
            return response()->json([
                'success' => false,
                'error'   => 'Coordenadas fuera de rango. Latitud: [-90, 90], Longitud: [-180, 180]',
            ], 422);
        }

        $order = DB::table('tuu_orders')
            ->where('id', $orderId)
            ->first();

        if (!$order || $order->delivery_type !== 'delivery') {
            return response()->json([
                'success' => false,
                'error'   => 'Pedido no encontrado',
            ], 404);
        }

        $lat = (float) $latitud;
        $lng = (float) $longitud;

        // Siempre actualizar rider_last_lat/lng en tuu_orders
        DB::table('tuu_orders')
            ->where('id', $orderId)
            ->update([
                'rider_last_lat' => $lat,
                'rider_last_lng' => $lng,
            ]);

        // Si el pedido tiene rider_id, persistir en rider_locations y emitir evento
        if ($order->rider_id) {
            $this->locationService->updateRiderLocation(
                riderId: $order->rider_id,
                lat: $lat,
                lng: $lng,
            );
        }

        return response()->json([
            'success' => true,
        ]);
    }
}
