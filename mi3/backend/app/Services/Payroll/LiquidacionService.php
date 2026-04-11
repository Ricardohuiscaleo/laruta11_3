<?php

namespace App\Services\Payroll;

use App\Models\AjusteSueldo;
use App\Models\Personal;
use App\Models\TuuOrder;
use App\Services\Shift\ShiftService;
use Carbon\Carbon;

class LiquidacionService
{
    public function __construct(
        private ShiftService $shiftService,
    ) {}

    /**
     * Calculate payroll for a worker in a given month and cost center context.
     *
     * Replicates exactly the getLiquidacion() function from PersonalApp.jsx.
     *
     * @param Personal $persona  The worker
     * @param string   $mes      Month in YYYY-MM format
     * @param string   $modoContexto  'all', 'ruta11', or 'seguridad'
     */
    public function calcular(Personal $persona, string $mes, string $modoContexto = 'all'): array
    {
        $allShifts = $this->shiftService->getShiftsForMonth($mes);
        $ajustes = AjusteSueldo::where('personal_id', $persona->id)
            ->where('mes', $mes . '-01')
            ->get();

        $personalId = $persona->id;

        // 1. Filter shifts by context
        $turnosFiltrados = array_filter($allShifts, function ($t) use ($modoContexto) {
            if ($modoContexto === 'seguridad') {
                return $this->shiftService->isShiftSeguridad($t);
            }
            if ($modoContexto === 'ruta11') {
                return !$this->shiftService->isShiftSeguridad($t);
            }
            return true; // 'all'
        });

        // 2. Count normal days (shifts where this person is the titular with normal/seguridad type)
        $diasNormales = count(array_filter($turnosFiltrados, fn($t) =>
            $this->getPersonalId($t) == $personalId
            && in_array($this->getTipo($t), ['normal', 'seguridad'])
        ));

        // 3. Replacements received (where this person is the titular being replaced)
        $reemplazosRecibidos = array_values(array_filter($turnosFiltrados, fn($t) =>
            $this->getPersonalId($t) == $personalId
            && in_array($this->getTipo($t), ['reemplazo', 'reemplazo_seguridad'])
        ));

        // 4. Replacements done (where this person is the replacer)
        $reemplazosRealizados = array_values(array_filter($turnosFiltrados, fn($t) =>
            $this->getReemplazadoPor($t) == $personalId
            && in_array($this->getTipo($t), ['reemplazo', 'reemplazo_seguridad'])
        ));

        // 5. Days worked
        $diasReemplazados = count($reemplazosRecibidos);
        $reemplazosHechos = count($reemplazosRealizados);
        $diasTrabajados = $modoContexto === 'seguridad'
            ? (30 - $diasReemplazados)
            : ($diasNormales + $reemplazosHechos);

        // 6. Base salary
        $sueldoBase = $this->determinarSueldoBase($persona, $modoContexto, $mes);

        // 7. Adjustments (only once, avoid double counting between cost centers)
        $hasRuta11Role = preg_match('/administrador|cajero|planchero/', $persona->rol ?? '');
        $includeAjustes = $modoContexto === 'all'
            || $modoContexto === 'ruta11'
            || ($modoContexto === 'seguridad' && !$hasRuta11Role);
        $totalAjustes = $includeAjustes ? $ajustes->sum('monto') : 0;

        // 8. Replacement totals (replicating PersonalApp.jsx logic)
        // totalReemplazando: what this person earns from doing replacements (only empresa pays)
        $totalReemplazando = collect($reemplazosRealizados)
            ->filter(fn($t) => $this->getPagoPor($t) === 'empresa')
            ->sum(fn($t) => (float) ($this->getMontoReemplazo($t) ?? 20000));

        // totalReemplazados: what is deducted because this person was replaced (empresa or empresa_adelanto)
        $totalReemplazados = collect($reemplazosRecibidos)
            ->filter(fn($t) => in_array($this->getPagoPor($t), ['empresa', 'empresa_adelanto']))
            ->sum(fn($t) => (float) ($this->getMontoReemplazo($t) ?? 20000));

        // 9. Total
        $total = (int) round($sueldoBase + $totalReemplazando - $totalReemplazados + $totalAjustes);

        return [
            'centro_costo' => $modoContexto,
            'sueldo_base' => (int) round($sueldoBase),
            'dias_trabajados' => $diasTrabajados,
            'dias_normales' => $diasNormales,
            'dias_reemplazados' => $diasReemplazados,
            'reemplazos_hechos' => $reemplazosHechos,
            'reemplazos_realizados' => $this->groupReemplazos($reemplazosRealizados),
            'reemplazos_recibidos' => $this->groupReemplazos($reemplazosRecibidos),
            'ajustes' => $includeAjustes ? $ajustes->toArray() : [],
            'total_ajustes' => $totalAjustes,
            'total' => $total,
        ];
    }

    /**
     * Determine base salary based on role and cost center context.
     *
     * Replicates the salary logic from PersonalApp.jsx getLiquidacion().
     */
    private function determinarSueldoBase(Personal $persona, string $contexto, string $mes): float
    {
        $roles = $persona->getRolesArray();
        $isOwner = in_array('dueño', $roles);

        if ($contexto === 'seguridad') {
            return (float) $persona->sueldo_base_seguridad;
        }

        if ($contexto === 'ruta11') {
            if ($isOwner) {
                return $this->getCashflowLiquidez($mes);
            }
            if (in_array('administrador', $roles)) {
                return (float) $persona->sueldo_base_admin;
            }
            if (in_array('cajero', $roles)) {
                return (float) $persona->sueldo_base_cajero;
            }
            if (in_array('planchero', $roles)) {
                return (float) $persona->sueldo_base_planchero;
            }
            return 0;
        }

        // contexto === 'all'
        $base11 = 0;
        if ($isOwner) {
            $base11 = $this->getCashflowLiquidez($mes);
        } elseif (in_array('administrador', $roles)) {
            $base11 = (float) $persona->sueldo_base_admin;
        } elseif (in_array('cajero', $roles)) {
            $base11 = (float) $persona->sueldo_base_cajero;
        } elseif (in_array('planchero', $roles)) {
            $base11 = (float) $persona->sueldo_base_planchero;
        }

        return $base11 + (float) $persona->sueldo_base_seguridad;
    }

    /**
     * Get cashflow liquidity for the owner's base salary.
     *
     * Queries tuu_orders for the month to calculate net revenue.
     */
    private function getCashflowLiquidez(string $mes): float
    {
        $startDate = Carbon::parse($mes . '-01')->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        return (float) TuuOrder::whereBetween('created_at', [$startDate, $endDate])
            ->sum('subtotal');
    }

    /**
     * Group replacement shifts by the other person involved.
     *
     * Replicates the gruposReemplazados/gruposReemplazando grouping from PersonalApp.jsx.
     */
    private function groupReemplazos(array $reemplazos): array
    {
        $grupos = [];

        foreach ($reemplazos as $t) {
            $tipo = $this->getTipo($t);
            $isRecibido = in_array($tipo, ['reemplazo', 'reemplazo_seguridad']);

            // For received replacements, group by the replacer (reemplazado_por)
            // For done replacements, group by the titular (personal_id)
            $key = $this->getReemplazadoPor($t) ?? $this->getPersonalId($t);
            $fecha = $this->getFecha($t);
            $dia = (int) Carbon::parse($fecha)->format('d');

            if (!isset($grupos[$key])) {
                $persona = Personal::find($key);
                $grupos[$key] = [
                    'personal_id' => $key,
                    'nombre' => $persona?->nombre ?? 'Desconocido',
                    'dias' => [],
                    'monto' => 0,
                    'pago_por' => $this->getPagoPor($t) ?? 'empresa',
                ];
            }

            $grupos[$key]['dias'][] = $dia;
            $grupos[$key]['monto'] += (float) ($this->getMontoReemplazo($t) ?? 20000);
        }

        return array_values($grupos);
    }

    // --- Accessors that work with both arrays and Turno models ---

    private function getPersonalId(array|object $t): ?int
    {
        return is_array($t) ? ($t['personal_id'] ?? null) : $t->personal_id;
    }

    private function getTipo(array|object $t): ?string
    {
        return is_array($t) ? ($t['tipo'] ?? null) : $t->tipo;
    }

    private function getReemplazadoPor(array|object $t): ?int
    {
        return is_array($t) ? ($t['reemplazado_por'] ?? null) : $t->reemplazado_por;
    }

    private function getMontoReemplazo(array|object $t): ?float
    {
        $val = is_array($t) ? ($t['monto_reemplazo'] ?? null) : $t->monto_reemplazo;
        return $val !== null ? (float) $val : null;
    }

    private function getPagoPor(array|object $t): ?string
    {
        return is_array($t) ? ($t['pago_por'] ?? null) : $t->pago_por;
    }

    private function getFecha(array|object $t): ?string
    {
        if (is_array($t)) {
            return $t['fecha'] ?? null;
        }
        return $t->fecha instanceof Carbon ? $t->fecha->format('Y-m-d') : $t->fecha;
    }
}
