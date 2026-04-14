<?php

namespace App\Services\Delivery;

use App\Events\OrderStatusUpdated;
use App\Events\RiderLocationUpdated;
use App\Models\DeliveryAssignment;
use App\Models\Personal;
use App\Models\TuuOrder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeliveryService
{
    /**
     * Estados válidos para pedidos de delivery activos.
     */
    private const ACTIVE_STATUSES = ['sent_to_kitchen', 'preparing', 'ready', 'out_for_delivery'];

    /**
     * Enum completo de estados permitidos para tuu_orders.
     */
    private const ALLOWED_STATUSES = [
        'pending',
        'sent_to_kitchen',
        'preparing',
        'ready',
        'out_for_delivery',
        'delivered',
        'completed',
        'cancelled',
    ];

    /**
     * Retorna pedidos activos de delivery con info del rider y última posición GPS.
     */
    public function getActiveOrders(): Collection
    {
        $statuses = self::ACTIVE_STATUSES;

        return DB::table('tuu_orders as o')
            ->leftJoin('personal as p', 'o.rider_id', '=', 'p.id')
            ->leftJoin('delivery_assignments as da', function ($join) {
                $join->on('da.order_id', '=', 'o.id')
                    ->whereIn('da.status', ['assigned', 'picked_up']);
            })
            ->where('o.delivery_type', 'delivery')
            ->whereIn('o.order_status', $statuses)
            ->select([
                'o.id',
                'o.order_number',
                'o.order_status',
                'o.delivery_type',
                'o.delivery_address',
                'o.delivery_fee',
                'o.rider_id',
                'o.rider_last_lat',
                'o.rider_last_lng',
                'o.tracking_url',
                'o.created_at',
                'p.nombre as rider_nombre',
                'p.foto_url as rider_foto_url',
                'p.telefono as rider_telefono',
                'da.id as assignment_id',
                'da.status as assignment_status',
                'da.assigned_at',
                'da.picked_up_at',
            ])
            ->orderBy('o.created_at', 'asc')
            ->get();
    }

    /**
     * Asigna un rider a un pedido: actualiza rider_id en tuu_orders,
     * crea DeliveryAssignment y emite eventos de broadcast.
     */
    public function assignRider(int $orderId, int $riderId, int $assignedBy): DeliveryAssignment
    {
        return DB::transaction(function () use ($orderId, $riderId, $assignedBy) {
            // Actualizar rider_id en tuu_orders
            DB::table('tuu_orders')
                ->where('id', $orderId)
                ->update(['rider_id' => $riderId]);

            // Cancelar asignaciones previas activas para este pedido
            DB::table('delivery_assignments')
                ->where('order_id', $orderId)
                ->whereIn('status', ['assigned', 'picked_up'])
                ->update(['status' => 'cancelled']);

            // Crear nueva asignación
            $assignment = DeliveryAssignment::create([
                'order_id'    => $orderId,
                'rider_id'    => $riderId,
                'assigned_by' => $assignedBy,
                'assigned_at' => now(),
                'status'      => 'assigned',
            ]);

            // Obtener datos del pedido y rider para el broadcast
            $order = DB::table('tuu_orders')->where('id', $orderId)->first();
            $rider = Personal::find($riderId);

            if ($order && $rider) {
                // Emitir RiderLocationUpdated para que el monitor sepa la asignación
                broadcast(new RiderLocationUpdated(
                    riderId: $riderId,
                    nombre: $rider->nombre,
                    latitud: (float) ($order->rider_last_lat ?? 0),
                    longitud: (float) ($order->rider_last_lng ?? 0),
                    pedidoAsignadoId: $orderId,
                    pedidoAsignadoOrderNumber: $order->order_number,
                ));

                // Emitir OrderStatusUpdated para notificar la asignación
                broadcast(new OrderStatusUpdated(
                    orderId: $orderId,
                    orderNumber: $order->order_number,
                    orderStatus: $order->order_status,
                    riderId: $riderId,
                    estimatedDeliveryTime: null,
                    updatedAt: now()->toISOString(),
                ));
            }

            return $assignment;
        });
    }

    /**
     * Actualiza el estado de un pedido, valida el enum y emite OrderStatusUpdated.
     * Si status='delivered', actualiza delivered_at en la asignación activa.
     */
    public function updateOrderStatus(int $orderId, string $status): object
    {
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \InvalidArgumentException(
                "Estado inválido: '{$status}'. Valores permitidos: " . implode(', ', self::ALLOWED_STATUSES)
            );
        }

        DB::table('tuu_orders')
            ->where('id', $orderId)
            ->update(['order_status' => $status]);

        // Si se marca como entregado, actualizar delivered_at en la asignación activa
        if ($status === 'delivered') {
            DB::table('delivery_assignments')
                ->where('order_id', $orderId)
                ->whereIn('status', ['assigned', 'picked_up'])
                ->update([
                    'status'       => 'delivered',
                    'delivered_at' => now(),
                ]);
        }

        $order = DB::table('tuu_orders')->where('id', $orderId)->first();

        broadcast(new OrderStatusUpdated(
            orderId: $orderId,
            orderNumber: $order->order_number,
            orderStatus: $status,
            riderId: $order->rider_id ?? null,
            estimatedDeliveryTime: null,
            updatedAt: now()->toISOString(),
        ));

        return $order;
    }

    /**
     * Retorna riders disponibles (rol incluye 'rider', sin asignación activa)
     * con su última posición GPS.
     */
    public function getAvailableRiders(): Collection
    {
        return DB::table('personal as p')
            ->leftJoin('delivery_assignments as da', function ($join) {
                $join->on('da.rider_id', '=', 'p.id')
                    ->whereIn('da.status', ['assigned', 'picked_up']);
            })
            ->leftJoin('rider_locations as rl', function ($join) {
                $join->on('rl.rider_id', '=', 'p.id')
                    ->whereRaw('rl.id = (
                        SELECT id FROM rider_locations
                        WHERE rider_id = p.id
                        ORDER BY created_at DESC
                        LIMIT 1
                    )');
            })
            ->where('p.activo', 1)
            ->where('p.rol', 'LIKE', '%rider%')
            ->whereNull('da.id')
            ->select([
                'p.id',
                'p.nombre',
                'p.foto_url',
                'p.telefono',
                'p.rol',
                'rl.latitud as last_lat',
                'rl.longitud as last_lng',
                'rl.created_at as last_location_at',
            ])
            ->orderBy('p.nombre')
            ->get();
    }
}
