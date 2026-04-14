<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TrackingController extends Controller
{
    /**
     * GET /api/v1/public/orders/{orderNumber}/tracking
     * Retorna datos de seguimiento públicos de un pedido.
     * No expone customer_phone, customer_email ni campos de pago.
     */
    public function show(string $orderNumber): JsonResponse
    {
        $order = DB::table('tuu_orders as o')
            ->leftJoin('personal as p', 'o.rider_id', '=', 'p.id')
            ->where('o.order_number', $orderNumber)
            ->select([
                'o.order_number',
                'o.order_status',
                'o.delivery_type',
                'o.customer_name',
                'o.delivery_address',
                'o.rider_last_lat',
                'o.rider_last_lng',
                'o.estimated_delivery_time',
                'p.nombre as rider_nombre',
                'p.foto_url as rider_foto_url',
            ])
            ->first();

        if (!$order || $order->delivery_type !== 'delivery') {
            return response()->json([
                'success' => false,
                'error'   => 'Pedido no encontrado',
            ], 404);
        }

        // Contar items del pedido
        $itemsCount = DB::table('tuu_order_items')
            ->where('order_id', DB::table('tuu_orders')->where('order_number', $orderNumber)->value('id'))
            ->count();

        return response()->json([
            'order_number'            => $order->order_number,
            'order_status'            => $order->order_status,
            'customer_name'           => $order->customer_name,
            'delivery_address'        => $order->delivery_address,
            'rider_name'              => $order->rider_nombre,
            'rider_photo_url'         => $order->rider_foto_url,
            'rider_lat'               => $order->rider_last_lat !== null ? (float) $order->rider_last_lat : null,
            'rider_lng'               => $order->rider_last_lng !== null ? (float) $order->rider_last_lng : null,
            'estimated_delivery_time' => $order->estimated_delivery_time,
            'items_count'             => $itemsCount,
        ]);
    }
}
