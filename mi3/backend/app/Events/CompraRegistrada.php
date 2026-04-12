<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new compra is registered.
 * Frontend listens on channel "compras" for real-time updates.
 */
class CompraRegistrada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $compra_id,
        public string $proveedor,
        public int $monto_total,
        public int $items_count,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('compras'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'compra.registrada';
    }

    public function broadcastWith(): array
    {
        return [
            'compra_id' => $this->compra_id,
            'proveedor' => $this->proveedor,
            'monto_total' => $this->monto_total,
            'items_count' => $this->items_count,
            'timestamp' => now()->toISOString(),
        ];
    }
}
