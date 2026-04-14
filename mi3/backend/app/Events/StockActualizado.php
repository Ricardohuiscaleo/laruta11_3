<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockActualizado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $tipo,
        public ?int $ingredient_id = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('compras')];
    }

    public function broadcastAs(): string
    {
        return 'stock.actualizado';
    }

    public function broadcastWith(): array
    {
        return [
            'tipo' => $this->tipo,
            'ingredient_id' => $this->ingredient_id,
            'timestamp' => now()->toISOString(),
        ];
    }
}
