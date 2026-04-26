<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new sale is received via webhook.
 * Carries updated KPIs so the admin Ventas section refreshes in realtime.
 */
class VentaNueva implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public array $kpis,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('admin.ventas'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'venta.nueva';
    }

    public function broadcastWith(): array
    {
        return [
            'kpis' => $this->kpis,
            'timestamp' => now()->toISOString(),
        ];
    }
}
