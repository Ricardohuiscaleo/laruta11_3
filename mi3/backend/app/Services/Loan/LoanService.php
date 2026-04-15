<?php

namespace App\Services\Loan;

use App\Events\LoanRequestedEvent;
use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Personal;
use App\Models\Prestamo;
use App\Models\Turno;
use App\Services\Notification\NotificationService;
use App\Services\Notification\PushNotificationService;
use App\Services\Notification\TelegramService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LoanService
{
    public function __construct(
        private NotificationService $notificationService,
        private TelegramService $telegramService,
        private PushNotificationService $pushNotificationService,
    ) {}

    /**
     * Create an adelanto de sueldo request for a worker.
     *
     * Validates: no active adelanto, amount <= proportional salary based on days worked.
     * Cuotas is always 1 (full deduction at end of month).
     * Creates a pending record and notifies admins.
     */
    public function solicitarPrestamo(int $personalId, float $monto, ?string $motivo): Prestamo
    {
        $personal = Personal::findOrFail($personalId);

        // Validate no active adelanto
        if ($this->getPrestamoActivo($personalId)) {
            throw new \InvalidArgumentException('Ya tienes un adelanto activo');
        }

        // Calculate max amount proportional to days worked this month
        $sueldoBase = $this->getSueldoBase($personal);
        $montoMaximo = $this->calcularMontoMaximo($personalId, $sueldoBase);

        if ($monto <= 0 || $monto > $montoMaximo) {
            throw new \InvalidArgumentException(
                "El monto debe ser entre \$1 y \$" . number_format($montoMaximo, 0, ',', '.') . " (proporcional a días trabajados)"
            );
        }

        $prestamo = Prestamo::create([
            'personal_id' => $personalId,
            'monto_solicitado' => $monto,
            'motivo' => $motivo,
            'cuotas' => 1, // Adelanto: always 1 (full deduction)
            'estado' => 'pendiente',
        ]);

        // Notify all active admins
        $admins = Personal::where('activo', 1)->get()->filter(fn($p) => $p->isAdmin());
        foreach ($admins as $admin) {
            $this->notificationService->crear(
                $admin->id,
                'sistema',
                'Nueva solicitud de adelanto',
                "{$personal->nombre} solicita un adelanto de \$" . number_format($monto, 0, ',', '.'),
                $prestamo->id,
                'prestamo'
            );
        }

        // Telegram notification (best-effort)
        try {
            $this->telegramService->sendToLaruta11(
                "💰 Solicitud de adelanto — {$personal->nombre} — \$" . number_format($monto, 0, ',', '.')
            );
        } catch (\Throwable $e) {
            Log::warning('Telegram adelanto: ' . $e->getMessage());
        }

        // Push notification to each admin (best-effort)
        foreach ($admins as $admin) {
            try {
                $this->pushNotificationService->enviar(
                    $admin->id,
                    '💰 Nueva solicitud de adelanto',
                    "{$personal->nombre} solicita \$" . number_format($monto, 0, ',', '.'),
                    '/admin/adelantos'
                );
            } catch (\Throwable $e) {
                Log::warning("Push adelanto admin {$admin->id}: " . $e->getMessage());
            }
        }

        // Broadcast realtime event (best-effort)
        try {
            broadcast(new LoanRequestedEvent($prestamo));
        } catch (\Throwable $e) {
            Log::warning('Broadcast LoanRequested: ' . $e->getMessage());
        }

        return $prestamo;
    }

    /**
     * Calculate the maximum adelanto amount based on days worked this month.
     *
     * Formula: (dias_con_turno / dias_totales_mes) × sueldo_base
     */
    public function calcularMontoMaximo(int $personalId, float $sueldoBase): float
    {
        $now = Carbon::now();
        $diasTotalesMes = $now->daysInMonth;

        // Count days with shifts in current month for this worker
        $diasConTurno = Turno::where('personal_id', $personalId)
            ->whereYear('fecha', $now->year)
            ->whereMonth('fecha', $now->month)
            ->distinct('fecha')
            ->count('fecha');

        if ($diasTotalesMes === 0) {
            return 0;
        }

        return floor(($diasConTurno / $diasTotalesMes) * $sueldoBase);
    }

    /**
     * Approve a pending adelanto.
     *
     * Updates status, creates a positive salary adjustment (category 'prestamo'),
     * and notifies the worker. Deduction is always at end of current month.
     */
    public function aprobar(Prestamo $prestamo, int $aprobadoPorId, float $montoAprobado, ?string $notas = null): void
    {
        if ($prestamo->estado !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden aprobar adelantos pendientes');
        }

        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');
        $mes = now()->format('Y-m');

        // Deduction is always end of current month (1st of next month when cron runs)
        $fechaInicioDescuento = Carbon::now()->startOfMonth()->format('Y-m-d');

        DB::transaction(function () use ($prestamo, $aprobadoPorId, $montoAprobado, $fechaInicioDescuento, $notas, $categoriaId, $mes) {
            $prestamo->update([
                'estado' => 'aprobado',
                'monto_aprobado' => $montoAprobado,
                'aprobado_por' => $aprobadoPorId,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicioDescuento,
                'notas_admin' => $notas,
            ]);

            // Create positive salary adjustment (adelanto disbursement)
            AjusteSueldo::create([
                'personal_id' => $prestamo->personal_id,
                'mes' => $mes . '-01',
                'monto' => $montoAprobado,
                'concepto' => 'Adelanto de sueldo aprobado',
                'categoria_id' => $categoriaId,
            ]);
        });

        // Notify the worker
        $this->notificationService->crear(
            $prestamo->personal_id,
            'sistema',
            '✅ Adelanto aprobado',
            "Tu adelanto por \$" . number_format($montoAprobado, 0, ',', '.') . " fue aprobado. Se descontará a fin de mes.",
            $prestamo->id,
            'prestamo'
        );
    }

    /**
     * Reject a pending adelanto and notify the worker.
     */
    public function rechazar(Prestamo $prestamo, int $aprobadoPorId, ?string $notas = null): void
    {
        if ($prestamo->estado !== 'pendiente') {
            throw new \InvalidArgumentException('Solo se pueden rechazar adelantos pendientes');
        }

        $prestamo->update([
            'estado' => 'rechazado',
            'aprobado_por' => $aprobadoPorId,
            'notas_admin' => $notas,
        ]);

        $this->notificationService->crear(
            $prestamo->personal_id,
            'sistema',
            '❌ Adelanto rechazado',
            'Tu solicitud de adelanto fue rechazada' . ($notas ? ": {$notas}" : ''),
            $prestamo->id,
            'prestamo'
        );
    }

    /**
     * Get the active adelanto for a worker (approved with pending deduction).
     */
    public function getPrestamoActivo(int $personalId): ?Prestamo
    {
        return Prestamo::where('personal_id', $personalId)
            ->where('estado', 'aprobado')
            ->whereColumn('cuotas_pagadas', '<', 'cuotas')
            ->first();
    }

    /**
     * Get all adelantos for a worker, ordered by creation date descending.
     */
    public function getPrestamosPorPersonal(int $personalId): Collection
    {
        return Prestamo::where('personal_id', $personalId)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get all adelantos (admin view), ordered by status priority and date.
     */
    public function getTodosPrestamos(): Collection
    {
        return Prestamo::with('personal')
            ->orderByRaw("FIELD(estado, 'pendiente', 'aprobado', 'rechazado', 'pagado', 'cancelado')")
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Process monthly deductions for all active adelantos.
     *
     * Runs on the 1st of each month via scheduler.
     * Deducts the full monto_aprobado in one shot and marks as 'pagado'.
     * Each adelanto is processed in its own transaction for resilience.
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
                    $montoDescuento = (int) round($prestamo->monto_aprobado);

                    // Create negative salary adjustment (full adelanto deduction)
                    AjusteSueldo::create([
                        'personal_id' => $prestamo->personal_id,
                        'mes' => $mes . '-01',
                        'monto' => -$montoDescuento,
                        'concepto' => "Descuento adelanto de sueldo - {$mesNombre}",
                        'categoria_id' => $categoriaId,
                    ]);

                    // Mark as fully paid immediately
                    $prestamo->update([
                        'cuotas_pagadas' => $prestamo->cuotas,
                        'estado' => 'pagado',
                    ]);

                    $resultados[] = [
                        'prestamo_id' => $prestamo->id,
                        'personal_id' => $prestamo->personal_id,
                        'monto' => $montoDescuento,
                        'estado_final' => 'pagado',
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
     * Get max adelanto info for a worker (used by frontend to show available amount).
     */
    public function getInfoAdelanto(int $personalId): array
    {
        $personal = Personal::findOrFail($personalId);
        $sueldoBase = $this->getSueldoBase($personal);

        $now = Carbon::now();
        $diasTotalesMes = $now->daysInMonth;
        $diasConTurno = Turno::where('personal_id', $personalId)
            ->whereYear('fecha', $now->year)
            ->whereMonth('fecha', $now->month)
            ->distinct('fecha')
            ->count('fecha');

        $montoMaximo = $this->calcularMontoMaximo($personalId, $sueldoBase);

        return [
            'sueldo_base' => $sueldoBase,
            'dias_trabajados' => $diasConTurno,
            'dias_totales_mes' => $diasTotalesMes,
            'monto_maximo' => $montoMaximo,
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
