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

// Feature: checklist-v2-asistencia, Property 1: Creación de checklists corresponde a turnos asignados

/**
 * Property 1: Creación de checklists corresponde a turnos asignados
 *
 * For any set of workers with/without shifts on a given date,
 * checklists are created only for workers with shifts, with correct rol and personal_id.
 *
 * **Validates: Requirements 1.1, 1.7**
 */
class CreationMatchesShiftsPropertyTest extends TestCase
{
    use RefreshDatabase;

    private ChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChecklistService();
        $this->seedTemplates();
    }

    private function cleanUp(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');
        ChecklistItem::query()->delete();
        ChecklistVirtual::query()->delete();
        Checklist::query()->delete();
        Turno::query()->delete();
        Personal::query()->delete();
        DB::statement('PRAGMA foreign_keys = ON');
    }

    private function seedTemplates(): void
    {
        $templates = [
            ['type' => 'apertura', 'rol' => 'cajero', 'item_order' => 1, 'description' => 'Encender PedidosYa', 'requires_photo' => false, 'active' => true],
            ['type' => 'apertura', 'rol' => 'cajero', 'item_order' => 2, 'description' => 'Verificar saldo', 'requires_photo' => false, 'active' => true],
            ['type' => 'apertura', 'rol' => 'planchero', 'item_order' => 1, 'description' => 'Sacar aderezos', 'requires_photo' => false, 'active' => true],
            ['type' => 'apertura', 'rol' => 'planchero', 'item_order' => 2, 'description' => 'FOTO exterior', 'requires_photo' => true, 'active' => true],
            ['type' => 'cierre', 'rol' => 'cajero', 'item_order' => 1, 'description' => 'Apagar PedidosYa', 'requires_photo' => false, 'active' => true],
            ['type' => 'cierre', 'rol' => 'planchero', 'item_order' => 1, 'description' => 'Guardar todo', 'requires_photo' => false, 'active' => true],
        ];

        foreach ($templates as $t) {
            ChecklistTemplate::create($t);
        }
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
     * Property 1: Only workers with shifts get checklists, with correct rol and personal_id.
     *
     * **Validates: Requirements 1.1, 1.7**
     *
     * @test
     */
    public function only_shift_workers_get_checklists_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();
            $this->seedTemplates();

            $fecha = now()->addDays(random_int(1, 60))->format('Y-m-d');

            $numWithShift = random_int(1, 4);
            $numWithoutShift = random_int(0, 3);

            $workersWithShift = [];
            $workersWithoutShift = [];

            for ($j = 0; $j < $numWithShift; $j++) {
                $rol = ['cajero', 'planchero'][random_int(0, 1)];
                $worker = $this->createWorker($rol);
                $workersWithShift[] = ['worker' => $worker, 'rol' => $rol];

                Turno::create([
                    'personal_id' => $worker->id,
                    'fecha' => $fecha,
                    'tipo' => 'normal',
                ]);
            }

            for ($j = 0; $j < $numWithoutShift; $j++) {
                $workersWithoutShift[] = $this->createWorker(['cajero', 'planchero'][random_int(0, 1)]);
            }

            $this->service->crearChecklistsDiarios($fecha);

            $checklists = Checklist::whereDate('scheduled_date', $fecha)->get();

            // Each shift worker should have 2 checklists (apertura + cierre)
            $this->assertEquals(
                $numWithShift * 2,
                $checklists->count(),
                "Iteration {$i}: Expected " . ($numWithShift * 2) . " checklists, got " . $checklists->count()
            );

            foreach ($workersWithShift as $ws) {
                $workerChecklists = $checklists->where('personal_id', $ws['worker']->id);
                $this->assertEquals(2, $workerChecklists->count(), "Iteration {$i}: Worker should have 2 checklists");

                foreach ($workerChecklists as $cl) {
                    $this->assertEquals($ws['rol'], $cl->rol);
                    $this->assertEquals($ws['worker']->id, $cl->personal_id);
                }
            }

            foreach ($workersWithoutShift as $worker) {
                $count = $checklists->where('personal_id', $worker->id)->count();
                $this->assertEquals(0, $count, "Iteration {$i}: Worker without shift should have 0 checklists");
            }
        }
    }
}
