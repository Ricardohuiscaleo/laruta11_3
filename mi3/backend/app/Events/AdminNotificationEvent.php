<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a notification is created for an admin user.
 * Sent to private-admin.{adminId} for the specific admin.
 */
class AdminNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $personalId,
        public string $titulo,
        public string $mensaje,
        public string $tipo,
        public ?string $referenciaTipo = null,
        public ?int $referenciaId = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("admin.{$this->personalId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'admin.notification';
    }

    public function broadcastWith(): array
    {
        return [
            'titulo' => $this->titulo,
            'mensaje' => $this->mensaje,
            'tipo' => $this->tipo,
            'referencia_tipo' => $this->referenciaTipo,
            'referencia_id' => $this->referenciaId,
            'timestamp' => now()->toISOString(),
        ];
    }
}
