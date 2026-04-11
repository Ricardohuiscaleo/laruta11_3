<?php

namespace Tests\Unit\ChecklistService;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Services\Checklist\ChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 3: Filtrado de checklists por rol del trabajador

/**
 * Property 3: Filtrado de checklists por rol del trabajador
 *
 * For any worker with a given rol and any set of mixed-rol checklists,
 * getChecklistsPendientes returns only checklists matching the worker's rol.
 *
 * **Validates: Requirement 2.1**
 */
class FilterByRolPropertyTest extends TestCase
{
    use RefreshDatabase;

    private ChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecklistService();
    }

    private function cleanUp(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        ChecklistItem::query()->delete();
        ChecklistVirtual::query()->delete();
        Checklist::query()->delete();
        Personal::query()->delete();
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

    /**
     * Property 3: getChecklistsPendientes returns only matching rol checklists.
     *
     * **Validates: Requirement 2.1**
     *
     * @test
     */
    public function pending_checklists_filtered_by_worker_rol_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $fecha = now()->addDays(random_int(1, 60))->format('Y-m-d');
            $workerRol = ['cajero', 'planchero'][random_int(0, 1)];
            $worker = $this->createWorker($workerRol);

            $numChecklists = random_int(2, 6);
            $expectedCount = 0;

            for ($j = 0; $j < $numChecklists; $j++) {
                $rol = ['cajero', 'planchero'][random_int(0, 1)];
                $type = ['apertura', 'cierre'][random_int(0, 1)];

                $checklist = Checklist::create([
                    'type' => $type,
                    'scheduled_date' => $fecha,
                    'scheduled_time' => '18:00:00',
                    'status' => 'pending',
                    'personal_id' => $worker->id,
                    'user_name' => $worker->nombre,
                    'rol' => $rol,
                    'checklist_mode' => 'presencial',
                    'total_items' => 2,
                    'completed_items' => 0,
                    'completion_percentage' => 0,
                ]);

                ChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'item_order' => 1,
                    'description' => 'Test item',
                    'requires_photo' => false,
                    'is_completed' => false,
                ]);

                if ($rol === $workerRol) {
                    $expectedCount++;
                }
            }

            $result = $this->service->getChecklistsPendientes($worker->id, $fecha);

            foreach ($result as $cl) {
                $this->assertEquals(
                    $workerRol,
                    $cl->rol,
                    "Iteration {$i}: Returned checklist rol '{$cl->rol}' should match worker rol '{$workerRol}'"
                );
            }

            $this->assertEquals(
                $expectedCount,
                $result->count(),
                "Iteration {$i}: Expected {$expectedCount} checklists for rol {$workerRol}"
            );
        }
    }
}
