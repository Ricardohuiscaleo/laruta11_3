<?php

namespace App\Services\Loan;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Personal;
use App\Models\Prestamo;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LoanService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Create a loan request for a worker.
     *
     * Validates: no active loan, amount <= base salary, installments 1-3.
     * Creates a pending record and notifies admins.
     */
    public function solicitarPrestamo(int $personalId, float $monto, int $cuotas, ?string $motivo): Prestamo
    {
        $personal = Personal::findOrFail($personalId);

        // Validate no active loan
        if ($this->getPrestamoActivo($personalId)) {
            throw new \InvalidArgumentException('Ya tienes un préstamo activo');
        }

        // Validate installments range
        if ($cuotas < 1 || $cuotas > 3) {
            throw new \InvalidArgumentException('Las cuotas deben ser entre 1 y 3');
        }

        // Validate amount against base salary
        $sueldoBase = $this->getSueldoBase($personal);
        if ($monto <= 0 || $monto > $sueldoBase) {
            throw new \InvalidArgumentException(
                "El monto debe ser entre \$1 y \$" . number_format($sueldoBase, 0, ',', '.')
            );
        }

        $prestamo = Prestamo::create([
            'personal_id' => $personalId,
            'monto_solicitado' => $monto,
            'motivo' => $motivo,
            'cuotas' => $cuotas,
            'estado' => 'pendiente',
        ]);

        // Notify all active admins
        $admins = Personal::where('activo', 1)->get()->filter(fn($p) => $p->isAdmin());
        foreach ($admins as $admin) {
            $this->notificationService->crear(
                $admin->id,
                'sistema',
                'Nueva solicitud de préstamo',
                "{$personal->nombre} solicita un préstamo de \$" . number_format($monto, 0, ',', '.'),
                $prestamo->id,
                'prestamo'
            );
        }

        return $prestamo;
    }

    /**
     * Approve a pending loan.
     *
     * Updates status, creates a positive salary adjustment (category 'prestamo'),
     * and notifies the worker.
     */
    public function aprobar(Prestamo $prestamo, int $aprobadoPorId, float $montoAprobado, ?string $fechaInicio = null, ?string $notas = null): void
    {
        if ($prestamo->estado !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden aprobar préstamos pendientes');
        }

        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');
        $mes = now()->format('Y-m');

        // Default: deductions start next month
        $fechaInicioDescuento = $fechaInicio
            ? Carbon::parse($fechaInicio)->format('Y-m-d')
            : Carbon::now()->addMonth()->startOfMonth()->format('Y-m-d');

        DB::transaction(function () use ($prestamo, $aprobadoPorId, $montoAprobado, $fechaInicioDescuento, $notas, $categoriaId, $mes) {
            $prestamo->update([
                'estado' => 'aprobado',
                'monto_aprobado' => $montoAprobado,
                'aprobado_por' => $aprobadoPorId,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicioDescuento,
                'notas_admin' => $notas,
            ]);

            // Create positive salary adjustment (loan disbursement)
            AjusteSueldo::create([
                'personal_id' => $prestamo->personal_id,
                'mes' => $mes . '-01',
                'monto' => $montoAprobado,
                'concepto' => 'Préstamo aprobado',
                'categoria_id' => $categoriaId,
            ]);
        });

        // Notify the worker
        $this->notificationService->crear(
            $prestamo->personal_id,
            'sistema',
            '✅ Préstamo aprobado',
            "Tu préstamo por \$" . number_format($montoAprobado, 0, ',', '.') . " fue aprobado",
            $prestamo->id,
            'prestamo'
        );
    }

    /**
     * Reject a pending loan and notify the worker.
     */
    public function rechazar(Prestamo $prestamo, int $aprobadoPorId, ?string $notas = null): void
    {
        if ($prestamo->estado !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden rechazar préstamos pendientes');
        }

        $prestamo->update([
            'estado' => 'rechazado',
            'aprobado_por' => $aprobadoPorId,
            'notas_admin' => $notas,
        ]);

        $this->notificationService->crear(
            $prestamo->personal_id,
            'sistema',
            '❌ Préstamo rechazado',
            'Tu solicitud de préstamo fue rechazada' . ($notas ? ": {$notas}" : ''),
            $prestamo->id,
            'prestamo'
        );
    }

    /**
     * Get the active loan for a worker (approved with pending installments).
     */
    public function getPrestamoActivo(int $personalId): ?Prestamo
    {
        return Prestamo::where('personal_id', $personalId)
            ->where('estado', 'aprobado')
            ->whereColumn('cuotas_pagadas', '<', 'cuotas')
            ->first();
    }

    /**
     * Get all loans for a worker, ordered by creation date descending.
     */
    public function getPrestamosPorPersonal(int $personalId): Collection
    {
        return Prestamo::where('personal_id', $personalId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get all loans (admin view), ordered by status priority and date.
     */
    public function getTodosPrestamos(): Collection
    {
        return Prestamo::with('personal')
            ->orderByRaw("FIELD(estado, 'pendiente', 'aprobado', 'rechazado', 'pagado', 'cancelado')")
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Process monthly installment deductions for all active loans.
     *
     * Runs on the 1st of each month via scheduler.
     * Creates negative salary adjustments and updates installment counts.
     * Each loan is processed in its own transaction for resilience.
     *
     * @return array{resultados: array, errores: array}
     */
    public function procesarDescuentosMensuales(): array
    {
        $mes = now()->format('Y-m');
        $mesNombre = now()->locale('es')->monthName;
        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');

        $prestamos = Prestamo::where('estado', 'aprobado')
            ->whereColumn('cuotas_pagadas', '<', 'cuotas')
            ->where('fecha_inicio_descuento', '<=', now()->startOfMonth()->format('Y-m-d'))
            ->get();

        $resultados = [];
        $errores = [];

        foreach ($prestamos as $prestamo) {
            try {
                DB::transaction(function () use ($prestamo, $mes, $mesNombre, $categoriaId, &$resultados) {
                    $montoCuota = (int) round($prestamo->monto_aprobado / $prestamo->cuotas);
                    $cuotaActual = $prestamo->cuotas_pagadas + 1;

                    // Create negative salary adjustment (installment deduction)
                    AjusteSueldo::create([
                        'personal_id' => $prestamo->personal_id,
                        'mes' => $mes . '-01',
                        'monto' => -$montoCuota,
                        'concepto' => "Cuota préstamo {$cuotaActual}/{$prestamo->cuotas} - {$mesNombre}",
                        'categoria_id' => $categoriaId,
                    ]);

                    $prestamo->increment('cuotas_pagadas');

                    // Mark as paid if all installments are done
                    if ($prestamo->cuotas_pagadas >= $prestamo->cuotas) {
                        $prestamo->update(['estado' => 'pagado']);
                    }

                    $resultados[] = [
                        'prestamo_id' => $prestamo->id,
                        'personal_id' => $prestamo->personal_id,
                        'cuota' => "{$cuotaActual}/{$prestamo->cuotas}",
                        'monto' => $montoCuota,
                        'estado_final' => $prestamo->estado,
                    ];
                });
            } catch (\Throwable $e) {
                $errores[] = [
                    'prestamo_id' => $prestamo->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'resultados' => $resultados,
            'errores' => $errores,
        ];
    }

    /**
     * Calculate the base salary for a worker based on their primary role.
     *
     * Role priority: administrador > cajero > planchero > seguridad.
     */
    public function getSueldoBase(Personal $personal): float
    {
        $roles = $personal->getRolesArray();

        if (in_array('administrador', $roles)) {
            return (float) ($personal->sueldo_base_admin ?: 0);
        }
        if (in_array('cajero', $roles)) {
            return (float) ($personal->sueldo_base_cajero ?: 0);
        }
        if (in_array('planchero', $roles)) {
            return (float) ($personal->sueldo_base_planchero ?: 0);
        }
        if (in_array('seguridad', $roles)) {
            return (float) ($personal->sueldo_base_seguridad ?: 0);
        }

        return 0;
    }
}
