<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a rider updates their GPS location.
 * Channels: delivery.monitor, rider.{rider_id}, order.{order_number} (if assigned)
 */
class RiderLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $riderId,
        public string $nombre,
        public float $latitud,
        public float $longitud,
        public ?int $pedidoAsignadoId,
        public ?string $pedidoAsignadoOrderNumber,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('delivery.monitor'),
            new PrivateChannel("rider.{$this->riderId}"),
        ];

        if ($this->pedidoAsignadoOrderNumber !== null) {
            $channels[] = new PrivateChannel("order.{$this->pedidoAsignadoOrderNumber}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'rider.location.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'rider_id'           => $this->riderId,
            'nombre'             => $this->nombre,
            'latitud'            => $this->latitud,
            'longitud'           => $this->longitud,
            'timestamp'          => now()->toISOString(),
            'pedido_asignado_id' => $this->pedidoAsignadoId,
        ];
    }
}
