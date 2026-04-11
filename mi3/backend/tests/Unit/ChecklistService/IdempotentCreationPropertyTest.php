<?php

namespace Tests\Unit\ChecklistService;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Models\Turno;
use App\Services\Checklist\ChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 2: Creación idempotente de checklists

/**
 * Property 2: Creación idempotente de checklists
 *
 * Running crearChecklistsDiarios twice with the same date/shifts produces
 * exactly the same result — no duplicates and no errors.
 *
 * **Validates: Requirement 1.6**
 */
class IdempotentCreationPropertyTest extends TestCase
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
        Turno::query()->delete();
        ChecklistTemplate::query()->delete();
        Personal::query()->delete();
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function seedTemplates(): void
    {
        ChecklistTemplate::create(['type' => 'apertura', 'rol' => 'cajero', 'item_order' => 1, 'description' => 'Encender PedidosYa', 'requires_photo' => false, 'active' => true]);
        ChecklistTemplate::create(['type' => 'apertura', 'rol' => 'planchero', 'item_order' => 1, 'description' => 'Sacar aderezos', 'requires_photo' => false, 'active' => true]);
        ChecklistTemplate::create(['type' => 'cierre', 'rol' => 'cajero', 'item_order' => 1, 'description' => 'Apagar PedidosYa', 'requires_photo' => false, 'active' => true]);
        ChecklistTemplate::create(['type' => 'cierre', 'rol' => 'planchero', 'item_order' => 1, 'description' => 'Guardar todo', 'requires_photo' => false, 'active' => true]);
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
     * Property 2: Running creation twice produces no duplicates.
     *
     * **Validates: Requirement 1.6**
     *
     * @test
     */
    public function double_creation_produces_no_duplicates_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();
            $this->seedTemplates();

            $fecha = now()->addDays(random_int(1, 60))->format('Y-m-d');
            $numWorkers = random_int(1, 4);

            for ($j = 0; $j < $numWorkers; $j++) {
                $rol = ['cajero', 'planchero'][random_int(0, 1)];
                $worker = $this->createWorker($rol);
                Turno::create([
                    'personal_id' => $worker->id,
                    'fecha' => $fecha,
                    'tipo' => 'normal',
                ]);
            }

            // First run
            $result1 = $this->service->crearChecklistsDiarios($fecha);
            $countAfterFirst = Checklist::whereDate('scheduled_date', $fecha)->count();

            // Second run — should create nothing new
            $result2 = $this->service->crearChecklistsDiarios($fecha);
            $countAfterSecond = Checklist::whereDate('scheduled_date', $fecha)->count();

            $this->assertEquals(
                $countAfterFirst,
                $countAfterSecond,
                "Iteration {$i}: Second run should not create duplicates"
            );

            $this->assertEquals(0, $result2['created'], "Iteration {$i}: Second run should create 0 checklists");
            $this->assertGreaterThan(0, $result2['skipped'], "Iteration {$i}: Second run should skip existing");
        }
    }
}
