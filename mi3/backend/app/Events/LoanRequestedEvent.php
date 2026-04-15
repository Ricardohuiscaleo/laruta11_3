<?php

namespace App\Events;

use App\Models\Personal;
use App\Models\Prestamo;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a worker requests a salary advance (adelanto).
 * Sent to private-admin.{adminId} for each active admin.
 */
class LoanRequestedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Prestamo $prestamo;

    public function __construct(Prestamo $prestamo)
    {
        $this->prestamo = $prestamo->loadMissing('personal');
    }

    public function broadcastOn(): array
    {
        $admins = Personal::where('activo', 1)->get()->filter(fn($p) => $p->isAdmin());

        return $admins->map(
            fn(Personal $admin) => new PrivateChannel("admin.{$admin->id}")
        )->values()->all();
    }

    public function broadcastAs(): string
    {
        return 'loan.requested';
    }

    public function broadcastWith(): array
    {
        return [
            'prestamo_id' => $this->prestamo->id,
            'personal_id' => $this->prestamo->personal_id,
            'personal_nombre' => $this->prestamo->personal->nombre ?? '',
            'monto_solicitado' => $this->prestamo->monto_solicitado,
            'motivo' => $this->prestamo->motivo,
            'estado' => $this->prestamo->estado,
            'created_at' => $this->prestamo->created_at?->toISOString(),
        ];
    }
}
