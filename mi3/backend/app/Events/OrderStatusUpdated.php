<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when an order status changes.
 * Channels: delivery.monitor, order.{order_number}
 */
class OrderStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderId,
        public string $orderNumber,
        public string $orderStatus,
        public ?int $riderId,
        public ?string $estimatedDeliveryTime,
        public string $updatedAt,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('delivery.monitor'),
            new PrivateChannel("order.{$this->orderNumber}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'               => $this->orderId,
            'order_number'           => $this->orderNumber,
            'order_status'           => $this->orderStatus,
            'rider_id'               => $this->riderId,
            'estimated_delivery_time' => $this->estimatedDeliveryTime,
            'updated_at'             => $this->updatedAt,
        ];
    }
}
