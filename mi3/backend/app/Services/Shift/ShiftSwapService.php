<?php

namespace App\Services\Shift;

use App\Models\Personal;
use App\Models\SolicitudCambioTurno;
use App\Models\Turno;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftSwapService
{
    /**
     * Create a shift swap request.
     */
    public function crearSolicitud(
        int $solicitanteId,
        int $compañeroId,
        string $fechaTurno,
        ?string $motivo
    ): SolicitudCambioTurno {
        $solicitud = SolicitudCambioTurno::create([
            'solicitante_id' => $solicitanteId,
            'compañero_id' => $compañeroId,
            'fecha_turno' => $fechaTurno,
            'motivo' => $motivo,
            'estado' => 'pendiente',
        ]);

        // Notify the proposed partner and admin
        $notificationService = app(NotificationService::class);

        $notificationService->crear(
            $compañeroId,
            'turno',
            'Solicitud de cambio de turno',
            "Te han solicitado un cambio para el {$fechaTurno}",
            $solicitud->id,
            'solicitud_cambio'
        );

        // Notify admins
        $admins = Personal::where('activo', 1)->get()->filter(fn($p) => $p->isAdmin());
        foreach ($admins as $admin) {
            $notificationService->crear(
                $admin->id,
                'turno',
                'Nueva solicitud de cambio de turno',
                "Solicitud de cambio para el {$fechaTurno}",
                $solicitud->id,
                'solicitud_cambio'
            );
        }

        return $solicitud;
    }

    /**
     * Approve a shift swap request. Creates replacement shifts.
     */
    public function aprobar(SolicitudCambioTurno $solicitud, int $aprobadoPorId): void
    {
        DB::transaction(function () use ($solicitud, $aprobadoPorId) {
            $solicitud->update([
                'estado' => 'aprobada',
                'aprobado_por' => $aprobadoPorId,
            ]);

            // Create replacement shift
            Turno::create([
                'personal_id' => $solicitud->solicitante_id,
                'fecha' => $solicitud->fecha_turno,
                'tipo' => 'reemplazo',
                'reemplazado_por' => $solicitud->compañero_id,
                'monto_reemplazo' => 20000,
                'pago_por' => 'empresa',
            ]);

            // Notify both workers
            $notificationService = app(NotificationService::class);

            $notificationService->crear(
                $solicitud->solicitante_id,
                'turno',
                'Cambio aprobado',
                "Tu cambio del {$solicitud->fecha_turno->format('Y-m-d')} fue aprobado",
                $solicitud->id,
                'solicitud_cambio'
            );

            $notificationService->crear(
                $solicitud->compañero_id,
                'turno',
                'Cambio aprobado',
                "El cambio del {$solicitud->fecha_turno->format('Y-m-d')} fue aprobado",
                $solicitud->id,
                'solicitud_cambio'
            );
        });
    }

    /**
     * Reject a shift swap request.
     */
    public function rechazar(SolicitudCambioTurno $solicitud, int $aprobadoPorId): void
    {
        $solicitud->update([
            'estado' => 'rechazada',
            'aprobado_por' => $aprobadoPorId,
        ]);

        app(NotificationService::class)->crear(
            $solicitud->solicitante_id,
            'turno',
            'Cambio rechazado',
            "Tu solicitud de cambio del {$solicitud->fecha_turno->format('Y-m-d')} fue rechazada",
            $solicitud->id,
            'solicitud_cambio'
        );
    }

    /**
     * Get available partners for a shift swap.
     *
     * Filters by: active, same cost center, excludes the requester.
     */
    public function getCompañerosDisponibles(Personal $solicitante): Collection
    {
        $roles = $solicitante->getRolesArray();
        $isSeguridad = in_array('seguridad', $roles);

        return Personal::where('activo', 1)
            ->where('id', '!=', $solicitante->id)
            ->get()
            ->filter(function ($p) use ($isSeguridad) {
                $pRoles = $p->getRolesArray();
                if ($isSeguridad) {
                    return in_array('seguridad', $pRoles);
                }
                // For ruta11 workers, include anyone who has a ruta11 role
                return !in_array('seguridad', $pRoles)
                    || count(array_intersect($pRoles, ['cajero', 'planchero', 'administrador'])) > 0;
            })
            ->values();
    }

    /**
     * Get swap requests for a specific worker.
     */
    public function getSolicitudesForPersonal(int $personalId): Collection
    {
        return SolicitudCambioTurno::with(['solicitante', 'compañero', 'aprobadoPor'])
            ->where('solicitante_id', $personalId)
            ->orWhere('compañero_id', $personalId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get all pending swap requests (for admin).
     */
    public function getSolicitudesPendientes(): Collection
    {
        return SolicitudCambioTurno::with(['solicitante', 'compañero'])
            ->where('estado', 'pendiente')
            ->orderByDesc('created_at')
            ->get();
    }
}
