<?php

namespace App\Services\Notification;

use App\Models\NotificacionMi3;

class NotificationService
{
    /**
     * Create a notification for a worker.
     */
    public function crear(
        int $personalId,
        string $tipo,
        string $titulo,
        string $mensaje,
        ?int $referenciaId = null,
        ?string $referenciaTipo = null
    ): NotificacionMi3 {
        return NotificacionMi3::create([
            'personal_id' => $personalId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'referencia_id' => $referenciaId,
            'referencia_tipo' => $referenciaTipo,
        ]);
    }

    /**
     * Get notifications for a worker with unread count.
     */
    public function getForPersonal(int $personalId): array
    {
        $notificaciones = NotificacionMi3::where('personal_id', $personalId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $noLeidas = NotificacionMi3::where('personal_id', $personalId)
            ->where('leida', 0)
            ->count();

        return [
            'data' => $notificaciones,
            'no_leidas' => $noLeidas,
        ];
    }

    /**
     * Mark a notification as read. Returns true if updated.
     */
    public function marcarLeida(int $id, int $personalId): bool
    {
        return NotificacionMi3::where('id', $id)
            ->where('personal_id', $personalId)
            ->update(['leida' => 1]) > 0;
    }

    /**
     * Mark all notifications as read for a worker.
     */
    public function marcarTodasLeidas(int $personalId): int
    {
        return NotificacionMi3::where('personal_id', $personalId)
            ->where('leida', 0)
            ->update(['leida' => 1]);
    }
}
