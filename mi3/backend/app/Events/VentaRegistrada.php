<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VentaRegistrada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $order_number,
        public float $monto,
        public string $source,
        public ?string $customer_name = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('compras')];
    }

    public function broadcastAs(): string
    {
        return 'venta.registrada';
    }

    public function broadcastWith(): array
    {
        return [
            'order_number' => $this->order_number,
            'monto' => $this->monto,
            'source' => $this->source,
            'customer_name' => $this->customer_name,
            'timestamp' => now()->toISOString(),
        ];
    }
}
