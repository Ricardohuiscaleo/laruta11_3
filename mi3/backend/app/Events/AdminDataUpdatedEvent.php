<?php

namespace App\Events;

use App\Models\Personal;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Generic broadcast event for admin-relevant data changes.
 * Sent to private-admin.{adminId} for each active admin.
 */
class AdminDataUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $section,
        public string $action,
        public ?array $data = null,
    ) {}

    public function broadcastOn(): array
    {
        $admins = Personal::where('activo', 1)->get()->filter(fn($p) => $p->isAdmin());

        return $admins->map(
            fn(Personal $admin) => new PrivateChannel("admin.{$admin->id}")
        )->values()->all();
    }

    public function broadcastAs(): string
    {
        return 'admin.data.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'section' => $this->section,
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toISOString(),
        ];
    }
}
