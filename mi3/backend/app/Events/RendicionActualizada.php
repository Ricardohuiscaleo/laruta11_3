<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a rendición is created, approved, or rejected.
 * Frontend listens on channel "compras" for real-time updates.
 */
class RendicionActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $rendicion_id,
        public string $estado,
        public float $saldo_nuevo,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('compras')];
    }

    public function broadcastAs(): string
    {
        return 'rendicion.actualizada';
    }

    public function broadcastWith(): array
    {
        return [
            'rendicion_id' => $this->rendicion_id,
            'estado' => $this->estado,
            'saldo_nuevo' => $this->saldo_nuevo,
            'timestamp' => now()->toISOString(),
        ];
    }
}
