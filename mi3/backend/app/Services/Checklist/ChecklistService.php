<?php

namespace App\Services\Checklist;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChecklistService
{
    /**
     * Get active templates from checklist_templates filtered by type and rol.
     */
    public function getTemplates(string $type, string $rol): Collection
    {
        return ChecklistTemplate::active()
            ->byType($type)
            ->byRol($rol)
            ->orderBy('item_order')
            ->get();
    }

    /**
     * Create daily checklists (apertura + cierre) for all workers with shifts on the given date.
     * Reads templates from DB by rol, skips duplicates.
     *
     * @return array{created: int, skipped: int}
     */
    public function crearChecklistsDiarios(string $fecha): array
    {
        $created = 0;
        $skipped = 0;

        // Get all shifts for the date (including replacements)
        $turnos = Turno::whereDate('fecha', $fecha)->get();

        foreach ($turnos as $turno) {
            // Determine the actual worker: if reemplazado_por is set, the replacement works
            $personalId = $turno->reemplazado_por ?: $turno->personal_id;
            $personal = Personal::find($personalId);

            if (!$personal) {
                continue;
            }

            // Determine the worker's rol for checklist purposes
            $roles = $personal->getRolesArray();
            $checklistRoles = array_intersect($roles, ['cajero', 'planchero']);

            if (empty($checklistRoles)) {
                continue;
            }

            foreach ($checklistRoles as $rol) {
                foreach (['apertura', 'cierre'] as $type) {
                    // Skip if checklist already exists for this personal_id, rol, type, date
                    $exists = Checklist::where('personal_id', $personalId)
                        ->where('rol', $rol)
                        ->where('type', $type)
                        ->whereDate('scheduled_date', $fecha)
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    $templates = $this->getTemplates($type, $rol);

                    if ($templates->isEmpty()) {
                        continue;
                    }

                    $checklist = Checklist::create([
                        'type' => $type,
                        'scheduled_date' => $fecha,
                        'scheduled_time' => $type === 'apertura' ? '18:00:00' : '02:00:00',
                        'status' => 'pending',
                        'personal_id' => $personalId,
                        'user_name' => $personal->nombre,
                        'rol' => $rol,
                        'checklist_mode' => 'presencial',
                        'total_items' => $templates->count(),
                        'completed_items' => 0,
                        'completion_percentage' => 0,
                    ]);

                    foreach ($templates as $template) {
                        $itemData = [
                            'checklist_id' => $checklist->id,
                            'item_order' => $template->item_order,
                            'description' => $template->description,
                            'item_type' => $template->item_type ?? 'standard',
                            'requires_photo' => $template->requires_photo,
                            'is_completed' => false,
                        ];

                        // For cash_verification items, query saldo esperado from caja_movimientos
                        if (($template->item_type ?? 'standard') === 'cash_verification') {
                            $saldoEsperado = DB::table('caja_movimientos')
                                ->orderByDesc('id')
                                ->value('saldo_nuevo') ?? 0;
                            $itemData['cash_expected'] = $saldoEsperado;
                        }

                        ChecklistItem::create($itemData);
                    }

                    $created++;
                }
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * Get pending checklists for a worker on a given date, filtered by the worker's rol.
     */
    public function getChecklistsPendientes(int $personalId, string $fecha): Collection
    {
        $personal = Personal::findOrFail($personalId);
        $roles = array_intersect($personal->getRolesArray(), ['cajero', 'planchero']);

        return Checklist::with('items')
            ->where('personal_id', $personalId)
            ->whereDate('scheduled_date', $fecha)
            ->whereIn('rol', $roles)
            ->pendientes()
            ->orderBy('type')
            ->get();
    }

    /**
     * Mark a checklist item as completed.
     * Validates photo requirement, records timestamp, updates progress.
     *
     * @return array{item: ChecklistItem, checklist: Checklist}
     */
    public function marcarItemCompletado(int $itemId, int $personalId): array
    {
        $item = ChecklistItem::findOrFail($itemId);
        $checklist = $item->checklist;

        // Validate the checklist belongs to this worker
        if ($checklist->personal_id !== $personalId) {
            throw new \InvalidArgumentException('No tienes permiso para completar este ítem');
        }

        // Validate photo requirement
        if ($item->requires_photo && empty($item->photo_url)) {
            throw new \InvalidArgumentException('Este ítem requiere una foto antes de ser completado');
        }

        // Toggle: if already completed, unmark it
        if ($item->is_completed) {
            $item->update([
                'is_completed' => false,
                'completed_at' => null,
            ]);

            // Update checklist progress
            $completedCount = $checklist->items()->where('is_completed', true)->count();
            $totalCount = $checklist->total_items;
            $percentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0;

            $checklist->update([
                'completed_items' => $completedCount,
                'completion_percentage' => $percentage,
                'status' => $completedCount === 0 ? 'pending' : 'active',
            ]);

            $checklist->refresh();
            return ['item' => $item, 'checklist' => $checklist];
        }

        $item->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Update checklist progress
        $completedCount = $checklist->items()->where('is_completed', true)->count();
        $totalCount = $checklist->total_items;
        $percentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0;

        $checklist->update([
            'completed_items' => $completedCount,
            'completion_percentage' => $percentage,
            'status' => $completedCount > 0 && $checklist->status === 'pending' ? 'active' : $checklist->status,
            'started_at' => $checklist->started_at ?? now(),
        ]);

        $checklist->refresh();

        return ['item' => $item, 'checklist' => $checklist];
    }

    /**
     * Mark a checklist as completed. Records completion time.
     */
    public function completarChecklist(int $checklistId, int $personalId): Checklist
    {
        $checklist = Checklist::findOrFail($checklistId);

        if ($checklist->personal_id !== $personalId) {
            throw new \InvalidArgumentException('No tienes permiso para completar este checklist');
        }

        // Already completed — return current state
        if ($checklist->status === 'completed') {
            return $checklist;
        }

        // Block completion if any cash_verification item is incomplete
        $incompleteCashItems = $checklist->items()
            ->where('item_type', 'cash_verification')
            ->where('is_completed', false)
            ->exists();

        if ($incompleteCashItems) {
            throw new \InvalidArgumentException('Debes completar la verificación de caja antes de finalizar el checklist');
        }

        $checklist->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        return $checklist->refresh();
    }

    /**
     * Enable a virtual checklist for a worker.
     */
    public function habilitarChecklistVirtual(int $personalId, int $checklistId): ChecklistVirtual
    {
        return ChecklistVirtual::create([
            'checklist_id' => $checklistId,
            'personal_id' => $personalId,
            'created_at' => now(),
        ]);
    }

    /**
     * Complete a virtual checklist with an improvement idea.
     * Validates minimum 20 characters for the idea.
     */
    public function completarChecklistVirtual(int $virtualId, int $personalId, string $ideaMejora): ChecklistVirtual
    {
        $virtual = ChecklistVirtual::findOrFail($virtualId);

        if ($virtual->personal_id !== $personalId) {
            throw new \InvalidArgumentException('No tienes permiso para completar este checklist virtual');
        }

        if (mb_strlen(trim($ideaMejora)) < 20) {
            throw new \InvalidArgumentException('La idea de mejora debe tener al menos 20 caracteres');
        }

        // Already completed — return current state
        if ($virtual->completed_at !== null) {
            return $virtual;
        }

        $virtual->update([
            'improvement_idea' => trim($ideaMejora),
            'completed_at' => now(),
        ]);

        // Also mark the associated checklist as completed
        $checklist = $virtual->checklist;
        if ($checklist && $checklist->status !== 'completed') {
            $checklist->update([
                'status' => 'completed',
                'completed_at' => now(),
                'checklist_mode' => 'virtual',
            ]);
        }

        return $virtual->refresh();
    }

    /**
     * Get available virtual checklist for a worker on a given date.
     */
    public function getChecklistVirtualDisponible(int $personalId, string $fecha): ?ChecklistVirtual
    {
        return ChecklistVirtual::where('personal_id', $personalId)
            ->whereNull('completed_at')
            ->whereHas('checklist', function ($q) use ($fecha) {
                $q->whereDate('scheduled_date', $fecha);
            })
            ->with('checklist')
            ->first();
    }

    /**
     * Verify cash for a cash_verification item.
     *
     * @return array{item: ChecklistItem, checklist: Checklist}
     */
    public function verificarCaja(int $itemId, int $personalId, bool $confirmed, ?float $actualAmount = null): array
    {
        $item = ChecklistItem::findOrFail($itemId);
        $checklist = $item->checklist;

        if ($checklist->personal_id !== $personalId) {
            throw new \InvalidArgumentException('No tienes permiso para verificar este ítem');
        }

        if ($item->item_type !== 'cash_verification') {
            throw new \InvalidArgumentException('Este ítem no es de verificación de caja');
        }

        // Idempotent: if already completed, return current state
        if ($item->is_completed) {
            return ['item' => $item, 'checklist' => $checklist];
        }

        // Use the cash_expected that was shown to the cashier (set at checklist load time)
        // Do NOT refresh here — the cashier verified against the amount they saw
        $cashExpected = (float) ($item->cash_expected ?? 0);

        // Always receive actual_amount from frontend (cashier always enters physical count)
        $cashActual = (float) ($actualAmount ?? $cashExpected);
        $difference = $cashActual - $cashExpected;
        $result = abs($difference) < 1 ? 'ok' : 'discrepancia'; // tolerance of $1 for rounding

        $item->update([
            'cash_actual' => $cashActual,
            'cash_difference' => $difference,
            'cash_result' => $result,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        // Update checklist progress
        $completedCount = $checklist->items()->where('is_completed', true)->count();
        $totalCount = $checklist->total_items;
        $percentage = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 2) : 0;

        $checklist->update([
            'completed_items' => $completedCount,
            'completion_percentage' => $percentage,
            'status' => $completedCount > 0 && $checklist->status === 'pending' ? 'active' : $checklist->status,
            'started_at' => $checklist->started_at ?? now(),
        ]);

        $checklist->refresh();
        $item->refresh();

        // Send notifications (best-effort, non-blocking)
        $this->enviarNotificacionesCaja($item, $checklist);

        return ['item' => $item, 'checklist' => $checklist];
    }

    /**
     * Send notifications after cash verification.
     */
    protected function enviarNotificacionesCaja(ChecklistItem $item, Checklist $checklist): void
    {
        try {
            $telegram = app(\App\Services\Notification\TelegramService::class);
            $nombre = $checklist->user_name ?? 'Cajero';
            $tipo = ucfirst($checklist->type); // Apertura/Cierre
            $fecha = $checklist->scheduled_date?->format('d/m/Y') ?? now()->format('d/m/Y');
            $expected = number_format((float) $item->cash_expected, 0, ',', '.');

            if ($item->cash_result === 'ok') {
                $msg = "✅ Caja verificada por {$nombre} — Saldo: \${$expected} — {$tipo} {$fecha}";
                $telegram->sendToLaruta11($msg);
            } else {
                $actual = number_format((float) $item->cash_actual, 0, ',', '.');
                $diff = (float) $item->cash_difference;
                $absDiff = number_format(abs($diff), 0, ',', '.');
                $tipo_diff = $diff > 0 ? 'sobrante' : 'faltante';

                $msg = "⚠️ Discrepancia de caja — {$nombre} — Esperado: \${$expected} — Real: \${$actual} — Diferencia: \${$absDiff} ({$tipo_diff}) — {$tipo} {$fecha}";
                $telegram->sendToLaruta11($msg);

                // Create notifications for each active admin
                $admins = Personal::where('activo', true)
                    ->where(function ($q) {
                        $q->where('rol', 'LIKE', '%administrador%')
                          ->orWhere('rol', 'LIKE', '%dueño%');
                    })
                    ->get();

                $pushService = app(\App\Services\Notification\PushNotificationService::class);

                foreach ($admins as $admin) {
                    \App\Models\NotificacionMi3::create([
                        'personal_id' => $admin->id,
                        'tipo' => 'discrepancia_caja',
                        'titulo' => 'Discrepancia de Caja',
                        'mensaje' => "Cajero: {$nombre}\nEsperado: \${$expected}\nReal: \${$actual}\nDiferencia: \${$absDiff} ({$tipo_diff})\n{$tipo} {$fecha}",
                    ]);

                    $pushService->enviar(
                        $admin->id,
                        '⚠️ Discrepancia de Caja',
                        "Diferencia: \${$absDiff} ({$tipo_diff}) — {$nombre}",
                        '/admin/checklists',
                        'high'
                    );
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Cash verification notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Get improvement ideas from completed virtual checklists, ordered by date desc.
     */
    public function getIdeasMejora(): Collection
    {
        return ChecklistVirtual::whereNotNull('completed_at')
            ->whereNotNull('improvement_idea')
            ->with(['personal', 'checklist'])
            ->orderByDesc('completed_at')
            ->get();
    }

    /**
     * Get checklists for admin view with optional date and status filters.
     */
    public function getChecklistsAdmin(string $fecha, ?string $status = null): Collection
    {
        $query = Checklist::with(['personal', 'items'])
            ->whereDate('scheduled_date', $fecha);

        if ($status) {
            $query->where('status', $status);
        }

        return $query->orderBy('type')
            ->orderBy('rol')
            ->get();
    }

    /**
     * Get checklist detail with items, photos, and AI results.
     *
     * @return array{checklist: Checklist, items: Collection}
     */
    public function getDetalleChecklist(int $checklistId): array
    {
        $checklist = Checklist::with(['personal', 'items', 'virtual'])
            ->findOrFail($checklistId);

        return [
            'checklist' => $checklist,
            'items' => $checklist->items->sortBy('item_order')->values(),
        ];
    }
}
