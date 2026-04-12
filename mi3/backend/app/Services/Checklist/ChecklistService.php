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
                        ChecklistItem::create([
                            'checklist_id' => $checklist->id,
                            'item_order' => $template->item_order,
                            'description' => $template->description,
                            'requires_photo' => $template->requires_photo,
                            'is_completed' => false,
                        ]);
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
