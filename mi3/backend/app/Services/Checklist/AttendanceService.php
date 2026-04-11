<?php

namespace App\Services\Checklist;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Checklist;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceService
{
    /**
     * Check if a worker has attendance for a given date.
     * A worker is considered present if they completed at least the apertura checklist
     * (presencial or virtual).
     */
    public function tieneAsistencia(int $personalId, string $fecha): bool
    {
        // Check for completed presencial apertura checklist
        $presencial = Checklist::where('personal_id', $personalId)
            ->whereDate('scheduled_date', $fecha)
            ->where('type', 'apertura')
            ->where('status', 'completed')
            ->exists();

        if ($presencial) {
            return true;
        }

        // Check for completed virtual checklist linked to an apertura checklist
        $virtual = ChecklistVirtual::where('personal_id', $personalId)
            ->whereNotNull('completed_at')
            ->whereHas('checklist', function ($q) use ($fecha) {
                $q->whereDate('scheduled_date', $fecha);
            })
            ->exists();

        return $virtual;
    }

    /**
     * Detect absences for a given date.
     * Query shifts without completed checklist (neither presencial nor virtual),
     * create ajuste_sueldo -40000 with category "inasistencia".
     *
     * @return array{ausentes: array, total: int}
     */
    public function detectarAusencias(string $fecha): array
    {
        $ausentes = [];

        $turnos = Turno::whereDate('fecha', $fecha)->get();

        $categoriaInasistencia = AjusteCategoria::where('slug', 'inasistencia')->first();

        foreach ($turnos as $turno) {
            // Determine the actual worker (replacement takes over)
            $personalId = $turno->reemplazado_por ?: $turno->personal_id;
            $personal = Personal::find($personalId);

            if (!$personal) {
                continue;
            }

            // Skip if already processed (worker may have multiple shifts via roles)
            if (in_array($personalId, array_column($ausentes, 'personal_id'))) {
                continue;
            }

            // Check attendance
            if ($this->tieneAsistencia($personalId, $fecha)) {
                continue;
            }

            // Check if ajuste already exists for this worker/date to avoid duplicates
            $mes = Carbon::parse($fecha)->format('Y-m-01');
            $existingAjuste = AjusteSueldo::where('personal_id', $personalId)
                ->where('mes', $mes)
                ->where('concepto', 'like', "%Inasistencia%{$fecha}%")
                ->exists();

            if ($existingAjuste) {
                continue;
            }

            try {
                AjusteSueldo::create([
                    'personal_id' => $personalId,
                    'mes' => $mes,
                    'monto' => -40000,
                    'concepto' => "Inasistencia {$fecha}",
                    'categoria_id' => $categoriaInasistencia?->id,
                    'notas' => "Descuento automático por inasistencia el {$fecha}. No completó checklist presencial ni virtual.",
                ]);

                $ausentes[] = [
                    'personal_id' => $personalId,
                    'nombre' => $personal->nombre,
                ];
            } catch (\Exception $e) {
                Log::error("Error creating ajuste_sueldo for personal_id {$personalId}: " . $e->getMessage());
                continue;
            }
        }

        return ['ausentes' => $ausentes, 'total' => count($ausentes)];
    }

    /**
     * Detect absent companion and enable virtual checklist for the present worker.
     * A shift pair is 1 cajero + 1 planchero assigned to the same date.
     *
     * @return array{habilitados: array, total: int}
     */
    public function detectarCompaneroAusente(string $fecha): array
    {
        $habilitados = [];

        $turnos = Turno::whereDate('fecha', $fecha)->get();

        // Group workers by date — find pairs (cajero + planchero)
        $workersByRol = ['cajero' => [], 'planchero' => []];

        foreach ($turnos as $turno) {
            $personalId = $turno->reemplazado_por ?: $turno->personal_id;
            $personal = Personal::find($personalId);

            if (!$personal) {
                continue;
            }

            $roles = array_intersect($personal->getRolesArray(), ['cajero', 'planchero']);
            foreach ($roles as $rol) {
                $workersByRol[$rol][] = $personal;
            }
        }

        // For each cajero-planchero pair, check if exactly one is absent
        foreach ($workersByRol['cajero'] as $cajero) {
            foreach ($workersByRol['planchero'] as $planchero) {
                $cajeroPresente = $this->tieneAsistenciaApertura($cajero->id, $fecha);
                $plancheroPresente = $this->tieneAsistenciaApertura($planchero->id, $fecha);

                // Both absent → no virtual for either
                if (!$cajeroPresente && !$plancheroPresente) {
                    continue;
                }

                // Both present → no virtual needed
                if ($cajeroPresente && $plancheroPresente) {
                    continue;
                }

                // Exactly one absent → enable virtual for the present worker
                $presenteId = $cajeroPresente ? $cajero->id : $planchero->id;
                $ausenteId = $cajeroPresente ? $planchero->id : $cajero->id;

                // Check if virtual already enabled
                $alreadyEnabled = ChecklistVirtual::where('personal_id', $presenteId)
                    ->whereHas('checklist', function ($q) use ($fecha) {
                        $q->whereDate('scheduled_date', $fecha);
                    })
                    ->exists();

                if ($alreadyEnabled) {
                    continue;
                }

                // Find or create a checklist to link the virtual to
                $checklist = Checklist::where('personal_id', $presenteId)
                    ->whereDate('scheduled_date', $fecha)
                    ->where('type', 'apertura')
                    ->first();

                if (!$checklist) {
                    // Create a virtual-mode checklist for the present worker
                    $personal = Personal::find($presenteId);
                    $rol = $personal ? (in_array('cajero', $personal->getRolesArray()) ? 'cajero' : 'planchero') : 'cajero';

                    $checklist = Checklist::create([
                        'type' => 'apertura',
                        'scheduled_date' => $fecha,
                        'scheduled_time' => '18:00:00',
                        'status' => 'pending',
                        'personal_id' => $presenteId,
                        'user_name' => $personal?->nombre,
                        'rol' => $rol,
                        'checklist_mode' => 'virtual',
                        'total_items' => 0,
                        'completed_items' => 0,
                        'completion_percentage' => 0,
                    ]);
                }

                $virtual = ChecklistVirtual::create([
                    'checklist_id' => $checklist->id,
                    'personal_id' => $presenteId,
                    'confirmation_text' => 'Al marcar este checklist confirmo que no asistiré a foodtruck porque mi compañero/a no asistirá este día. No se me descontará. No obstante estaré a disposición de otras tareas.',
                    'created_at' => now(),
                ]);

                $habilitados[] = [
                    'personal_id' => $presenteId,
                    'ausente_id' => $ausenteId,
                    'virtual_id' => $virtual->id,
                ];
            }
        }

        return ['habilitados' => $habilitados, 'total' => count($habilitados)];
    }

    /**
     * Check if a worker has started/completed their apertura checklist (presencial only).
     * Used for companion absence detection (before virtual is enabled).
     */
    private function tieneAsistenciaApertura(int $personalId, string $fecha): bool
    {
        return Checklist::where('personal_id', $personalId)
            ->whereDate('scheduled_date', $fecha)
            ->where('type', 'apertura')
            ->where('checklist_mode', 'presencial')
            ->whereIn('status', ['active', 'completed'])
            ->exists();
    }

    /**
     * Get monthly attendance summary for a worker.
     *
     * @return array{dias_trabajados: int, inasistencias: int, virtuales: int, monto_descuentos: float, total_turnos: int}
     */
    public function getResumenAsistenciaMensual(int $personalId, string $mes): array
    {
        $startOfMonth = Carbon::parse($mes)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($mes)->endOfMonth()->format('Y-m-d');

        // Total shifts assigned in the month
        $totalTurnos = Turno::where(function ($q) use ($personalId) {
                $q->where('personal_id', $personalId)
                  ->orWhere('reemplazado_por', $personalId);
            })
            ->whereDate('fecha', '>=', $startOfMonth)
            ->whereDate('fecha', '<=', $endOfMonth)
            ->count();

        // Days with completed presencial checklist (apertura)
        $diasPresencial = Checklist::where('personal_id', $personalId)
            ->where('type', 'apertura')
            ->where('checklist_mode', 'presencial')
            ->where('status', 'completed')
            ->whereDate('scheduled_date', '>=', $startOfMonth)
            ->whereDate('scheduled_date', '<=', $endOfMonth)
            ->distinct('scheduled_date')
            ->count('scheduled_date');

        // Days with completed virtual checklist
        $diasVirtual = ChecklistVirtual::where('personal_id', $personalId)
            ->whereNotNull('completed_at')
            ->whereHas('checklist', function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereDate('scheduled_date', '>=', $startOfMonth)
                  ->whereDate('scheduled_date', '<=', $endOfMonth);
            })
            ->count();

        $diasTrabajados = $diasPresencial + $diasVirtual;
        $inasistencias = max(0, $totalTurnos - $diasTrabajados);

        // Actual discount amount from ajustes_sueldo
        $categoriaInasistencia = AjusteCategoria::where('slug', 'inasistencia')->first();
        $montoDescuentos = 0.0;
        if ($categoriaInasistencia) {
            $montoDescuentos = abs((float) AjusteSueldo::where('personal_id', $personalId)
                ->where('categoria_id', $categoriaInasistencia->id)
                ->whereDate('mes', '>=', $startOfMonth)
                ->whereDate('mes', '<=', $endOfMonth)
                ->sum('monto'));
        }

        return [
            'dias_trabajados' => $diasTrabajados,
            'inasistencias' => $inasistencias,
            'virtuales' => $diasVirtual,
            'monto_descuentos' => $montoDescuentos,
            'total_turnos' => $totalTurnos,
        ];
    }

    /**
     * Get attendance summary for all workers for admin panel.
     */
    public function getResumenAsistenciaAdmin(string $mes): Collection
    {
        $startOfMonth = Carbon::parse($mes)->startOfMonth()->format('Y-m-d');
        $endOfMonth = Carbon::parse($mes)->endOfMonth()->format('Y-m-d');

        // Get all workers who had shifts in this month
        $personalIds = Turno::whereDate('fecha', '>=', $startOfMonth)
            ->whereDate('fecha', '<=', $endOfMonth)
            ->pluck('personal_id')
            ->merge(
                Turno::whereDate('fecha', '>=', $startOfMonth)
                    ->whereDate('fecha', '<=', $endOfMonth)
                    ->whereNotNull('reemplazado_por')
                    ->pluck('reemplazado_por')
            )
            ->unique();

        $result = collect();

        foreach ($personalIds as $personalId) {
            $personal = Personal::find($personalId);
            if (!$personal) {
                continue;
            }

            $resumen = $this->getResumenAsistenciaMensual($personalId, $mes);
            $resumen['personal_id'] = $personalId;
            $resumen['nombre'] = $personal->nombre;

            $result->push($resumen);
        }

        return $result;
    }
}
