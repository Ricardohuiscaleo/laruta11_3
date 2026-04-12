<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a new notification is created for a worker.
 * Frontend listens on channel "worker.{personalId}" for real-time updates.
 */
class NotificacionNueva implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $personalId,
        public string $titulo,
        public string $cuerpo,
        public string $tipo = 'general',
        public ?string $url = '/dashboard',
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("worker.{$this->personalId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.nueva';
    }

    public function broadcastWith(): array
    {
        return [
            'titulo' => $this->titulo,
            'cuerpo' => $this->cuerpo,
            'tipo' => $this->tipo,
            'url' => $this->url,
            'timestamp' => now()->toISOString(),
        ];
    }
}
