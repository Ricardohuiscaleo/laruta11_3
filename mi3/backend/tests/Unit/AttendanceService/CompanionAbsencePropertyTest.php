<?php

namespace Tests\Unit\AttendanceService;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Models\Turno;
use App\Services\Checklist\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 8: Detección de compañero ausente y habilitación de checklist virtual

/**
 * Property 8: Detección de compañero ausente y habilitación de checklist virtual
 *
 * For any shift pair (1 cajero + 1 planchero on the same date):
 * - If exactly one is absent, virtual is enabled for the present companion.
 * - If both are absent, no virtual is enabled for either.
 *
 * **Validates: Requirements 5.1, 6.2, 6.4**
 */
class CompanionAbsencePropertyTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AttendanceService();
    }

    private function cleanUp(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        AjusteSueldo::query()->delete();
        ChecklistItem::query()->delete();
        ChecklistVirtual::query()->delete();
        Checklist::query()->delete();
        Turno::query()->delete();
        Personal::query()->delete();
        AjusteCategoria::query()->delete();
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function createWorker(string $rol): Personal
    {
        return Personal::create([
            'nombre' => 'Worker ' . uniqid(),
            'rol' => $rol,
            'activo' => 1,
            'sueldo_base_cajero' => 0,
            'sueldo_base_planchero' => 0,
            'sueldo_base_admin' => 0,
            'sueldo_base_seguridad' => 0,
        ]);
    }

    private function createShift(int $personalId, string $fecha): Turno
    {
        return Turno::create([
            'personal_id' => $personalId,
            'fecha' => $fecha,
            'tipo' => 'normal',
        ]);
    }

    private function markPresent(int $personalId, string $fecha, string $rol): void
    {
        Checklist::create([
            'type' => 'apertura',
            'scheduled_date' => $fecha,
            'scheduled_time' => '18:00:00',
            'status' => 'completed',
            'personal_id' => $personalId,
            'user_name' => 'Worker',
            'rol' => $rol,
            'checklist_mode' => 'presencial',
            'total_items' => 1,
            'completed_items' => 1,
            'completion_percentage' => 100,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Property 8: Virtual enabled only when exactly one of the pair is absent.
     *
     * **Validates: Requirements 5.1, 6.2, 6.4**
     *
     * @test
     */
    public function virtual_enabled_only_when_exactly_one_absent_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $fecha = now()->addDays(random_int(1, 60))->format('Y-m-d');

            $cajero = $this->createWorker('cajero');
            $planchero = $this->createWorker('planchero');

            $this->createShift($cajero->id, $fecha);
            $this->createShift($planchero->id, $fecha);

            // Randomly decide presence/absence: 0=both absent, 1=cajero present only, 2=planchero present only, 3=both present
            $scenario = random_int(0, 3);

            $cajeroPresent = in_array($scenario, [1, 3]);
            $plancheroPresent = in_array($scenario, [2, 3]);

            if ($cajeroPresent) {
                $this->markPresent($cajero->id, $fecha, 'cajero');
            }
            if ($plancheroPresent) {
                $this->markPresent($planchero->id, $fecha, 'planchero');
            }

            // Run companion absence detection
            $result = $this->service->detectarCompaneroAusente($fecha);

            $virtualsCajero = ChecklistVirtual::where('personal_id', $cajero->id)
                ->whereHas('checklist', fn($q) => $q->whereDate('scheduled_date', $fecha))
                ->count();
            $virtualsPlanchero = ChecklistVirtual::where('personal_id', $planchero->id)
                ->whereHas('checklist', fn($q) => $q->whereDate('scheduled_date', $fecha))
                ->count();

            if ($scenario === 0) {
                // Both absent → no virtual for either
                $this->assertEquals(0, $virtualsCajero, "Iteration {$i} (both absent): Cajero should NOT get virtual");
                $this->assertEquals(0, $virtualsPlanchero, "Iteration {$i} (both absent): Planchero should NOT get virtual");
            } elseif ($scenario === 1) {
                // Cajero present, planchero absent → virtual for cajero
                $this->assertEquals(1, $virtualsCajero, "Iteration {$i} (cajero present): Cajero should get virtual");
                $this->assertEquals(0, $virtualsPlanchero, "Iteration {$i} (cajero present): Planchero should NOT get virtual");
            } elseif ($scenario === 2) {
                // Planchero present, cajero absent → virtual for planchero
                $this->assertEquals(0, $virtualsCajero, "Iteration {$i} (planchero present): Cajero should NOT get virtual");
                $this->assertEquals(1, $virtualsPlanchero, "Iteration {$i} (planchero present): Planchero should get virtual");
            } else {
                // Both present → no virtual needed
                $this->assertEquals(0, $virtualsCajero, "Iteration {$i} (both present): Cajero should NOT get virtual");
                $this->assertEquals(0, $virtualsPlanchero, "Iteration {$i} (both present): Planchero should NOT get virtual");
            }
        }
    }
}
