<?php

namespace App\Services\Shift;

use App\Models\Personal;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ShiftService
{
    /**
     * Get all shifts for a given month, combining DB shifts with dynamic 4x4 generation.
     *
     * Replicates the logic from caja3/api/personal/get_turnos.php.
     */
    public function getShiftsForMonth(string $mes): array
    {
        $startDate = Carbon::parse($mes . '-01')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->startOfDay();

        // 1. Get manual shifts from DB
        $dbShifts = Turno::with(['reemplazante', 'personal'])
            ->whereBetween('fecha', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('fecha')
            ->orderBy('personal_id')
            ->get();

        // 2. Filter out manual normal shifts for dynamic IDs (same logic as get_turnos.php)
        $dynamicIds = config('mi3.dynamic_shift_personal_ids');

        $filteredDbShifts = $dbShifts->filter(function ($t) use ($dynamicIds) {
            if (
                $t->tipo === 'normal'
                && empty($t->reemplazado_por)
                && in_array((int) $t->personal_id, $dynamicIds)
            ) {
                return false;
            }
            return true;
        });

        // 3. Generate dynamic 4x4 shifts
        $dynamicShifts = $this->generate4x4Shifts($startDate, $endDate, $dbShifts, $filteredDbShifts);

        // 4. Merge, convert DB shifts to arrays, sort
        $result = collect();

        foreach ($filteredDbShifts as $turno) {
            $result->push([
                'id' => $turno->id,
                'personal_id' => $turno->personal_id,
                'personal_nombre' => $turno->personal?->nombre,
                'fecha' => $turno->fecha->format('Y-m-d'),
                'tipo' => $turno->tipo,
                'is_dynamic' => false,
                'reemplazado_por' => $turno->reemplazado_por,
                'reemplazante_nombre' => $turno->reemplazante?->nombre,
                'monto_reemplazo' => $turno->monto_reemplazo,
                'pago_por' => $turno->pago_por,
            ]);
        }

        foreach ($dynamicShifts as $shift) {
            $result->push($shift);
        }

        return $result
            ->sortBy([['fecha', 'asc'], ['personal_id', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Get shifts filtered for a specific worker.
     */
    public function getShiftsForPersonal(int $personalId, string $mes): array
    {
        $allShifts = $this->getShiftsForMonth($mes);

        return array_values(array_filter($allShifts, function ($t) use ($personalId) {
            return $t['personal_id'] == $personalId || $t['reemplazado_por'] == $personalId;
        }));
    }

    /**
     * Check if a shift belongs to the seguridad context.
     *
     * Replicates isShiftSeguridad() from PersonalApp.jsx:
     * - tipo='seguridad' or 'reemplazo_seguridad' → true
     * - tipo='reemplazo' + titular has rol seguridad → true
     * - tipo='normal' → NEVER seguridad
     */
    public function isShiftSeguridad(array|Turno $turno): bool
    {
        $tipo = is_array($turno) ? ($turno['tipo'] ?? '') : $turno->tipo;
        $personalId = is_array($turno) ? ($turno['personal_id'] ?? null) : $turno->personal_id;

        if ($tipo === 'seguridad' || $tipo === 'reemplazo_seguridad') {
            return true;
        }

        if ($tipo === 'reemplazo') {
            $titular = Personal::find($personalId);
            return $titular && str_contains($titular->rol ?? '', 'seguridad');
        }

        return false;
    }

    /**
     * Generate dynamic 4x4 shifts for all configured cycles.
     *
     * Replicates the dynamic generation from get_turnos.php.
     */
    private function generate4x4Shifts(
        Carbon $start,
        Carbon $end,
        Collection $allDbShifts,
        Collection $filteredDbShifts
    ): array {
        $cycles = config('mi3.shift_cycles');
        $shifts = [];

        // Build personal name → ID map (and ID → name for display)
        $names = [];
        foreach ($cycles as $cycle) {
            $names[] = $cycle['person_a'];
            $names[] = $cycle['person_b'];
        }
        $personalData = Personal::whereIn('nombre', array_unique($names))
            ->where('activo', 1)
            ->get(['id', 'nombre']);
        $personalMap = $personalData->pluck('id', 'nombre')->toArray();
        $personalNames = $personalData->pluck('nombre', 'id')->toArray();

        // Track existing seguridad shifts to avoid duplicates (same as get_turnos.php)
        $turnosSegExistentes = [];
        foreach ($filteredDbShifts as $t) {
            if ($t->tipo === 'seguridad') {
                $turnosSegExistentes[$t->fecha->format('Y-m-d') . '_' . $t->personal_id] = true;
            }
        }

        // Track existing ruta11 shifts (non-seguridad) to avoid duplicates
        $turnosRutaExistentes = [];
        foreach ($filteredDbShifts as $t) {
            if ($t->tipo !== 'seguridad' && $t->tipo !== 'reemplazo_seguridad') {
                $turnosRutaExistentes[$t->fecha->format('Y-m-d') . '_' . $t->personal_id] = true;
                // If someone is being replaced, don't generate automatic shift for that person
                if (!empty($t->reemplazado_por)) {
                    $turnosRutaExistentes[$t->fecha->format('Y-m-d') . '_' . $t->reemplazado_por] = true;
                }
            }
        }

        foreach ($cycles as $cycleName => $cycle) {
            $baseDate = Carbon::parse($cycle['base_date']);
            $personAId = $personalMap[$cycle['person_a']] ?? null;
            $personBId = $personalMap[$cycle['person_b']] ?? null;

            if (!$personAId || !$personBId) {
                continue;
            }

            $isSeguridad = $cycleName === 'seguridad';
            $tipo = $isSeguridad ? 'seguridad' : 'normal';
            $existentes = $isSeguridad ? $turnosSegExistentes : $turnosRutaExistentes;

            $current = $start->copy();
            while ($current <= $end) {
                $diff = $baseDate->diffInDays($current, false);
                $pos = (($diff % 8) + 8) % 8;
                $personalId = $pos < 4 ? $personAId : $personBId;
                $fechaStr = $current->format('Y-m-d');

                $key = $fechaStr . '_' . $personalId;

                if (!isset($existentes[$key])) {
                    $idPrefix = $isSeguridad ? 'dyn_' : 'dyn_ruta_';
                    $shifts[] = [
                        'id' => $idPrefix . $fechaStr . '_' . $personalId,
                        'personal_id' => $personalId,
                        'personal_nombre' => $personalNames[$personalId] ?? null,
                        'fecha' => $fechaStr,
                        'tipo' => $tipo,
                        'is_dynamic' => true,
                        'reemplazado_por' => null,
                        'reemplazante_nombre' => null,
                        'monto_reemplazo' => $isSeguridad ? 20000 : null,
                        'pago_por' => $isSeguridad ? 'empresa' : null,
                    ];
                }

                $current->addDay();
            }
        }

        return $shifts;
    }
}
