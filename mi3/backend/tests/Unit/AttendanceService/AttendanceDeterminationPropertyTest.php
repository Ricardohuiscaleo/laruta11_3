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
use App\Services\Checklist\ChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 7: Determinación de asistencia por completado de checklist

/**
 * Property 7: Determinación de asistencia por completado de checklist
 *
 * For any worker with a shift (titular or replacement) on a given date:
 * - If they completed at least the apertura checklist (presencial or virtual), they are present without discount.
 * - If they completed nothing (neither presencial nor virtual), they are absent with ajuste -40000.
 *
 * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 5.5**
 */
class AttendanceDeterminationPropertyTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $attendanceService;
    private ChecklistService $checklistService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->attendanceService = new AttendanceService();
        $this->checklistService = new ChecklistService();
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

    private function seedInasistenciaCategory(): AjusteCategoria
    {
        return AjusteCategoria::create([
            'nombre' => 'Inasistencia',
            'slug' => 'inasistencia',
            'icono' => '❌',
        ]);
    }

    private function createWorker(string $rol, bool $isReplacement = false): Personal
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

    private function createShift(int $personalId, string $fecha, ?int $reemplazadoPor = null): Turno
    {
        return Turno::create([
            'personal_id' => $personalId,
            'fecha' => $fecha,
            'tipo' => $reemplazadoPor ? 'reemplazo' : 'normal',
            'reemplazado_por' => $reemplazadoPor,
        ]);
    }

    private function createCompletedChecklist(int $personalId, string $fecha, string $rol, string $mode = 'presencial'): Checklist
    {
        return Checklist::create([
            'type' => 'apertura',
            'scheduled_date' => $fecha,
            'scheduled_time' => '18:00:00',
            'status' => 'completed',
            'personal_id' => $personalId,
            'user_name' => 'Worker',
            'rol' => $rol,
            'checklist_mode' => $mode,
            'total_items' => 1,
            'completed_items' => 1,
            'completion_percentage' => 100,
            'completed_at' => now(),
        ]);
    }

    private function createCompletedVirtual(int $personalId, string $fecha, string $rol): void
    {
        $checklist = Checklist::create([
            'type' => 'apertura',
            'scheduled_date' => $fecha,
            'scheduled_time' => '18:00:00',
            'status' => 'completed',
            'personal_id' => $personalId,
            'user_name' => 'Worker',
            'rol' => $rol,
            'checklist_mode' => 'virtual',
            'total_items' => 0,
            'completed_items' => 0,
            'completion_percentage' => 0,
            'completed_at' => now(),
        ]);

        ChecklistVirtual::create([
            'checklist_id' => $checklist->id,
            'personal_id' => $personalId,
            'confirmation_text' => 'Confirmo...',
            'improvement_idea' => 'Una idea de mejora con más de 20 caracteres',
            'completed_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Property 7: Workers with completed apertura are present, workers without are absent with -40000.
     *
     * **Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5, 5.5**
     *
     * @test
     */
    public function attendance_determined_by_checklist_completion_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();
            $this->seedInasistenciaCategory();

            $fecha = now()->addDays(random_int(1, 60))->format('Y-m-d');
            $numWorkers = random_int(1, 4);

            $workers = [];
            for ($j = 0; $j < $numWorkers; $j++) {
                $rol = ['cajero', 'planchero'][random_int(0, 1)];
                // Randomly decide: titular shift or replacement shift
                $isReplacement = random_int(0, 1) === 1;

                $worker = $this->createWorker($rol);

                if ($isReplacement) {
                    // Create a titular who is being replaced
                    $titular = $this->createWorker($rol);
                    $this->createShift($titular->id, $fecha, $worker->id);
                } else {
                    $this->createShift($worker->id, $fecha);
                }

                // Randomly decide attendance scenario:
                // 0 = no checklist (absent), 1 = presencial completed, 2 = virtual completed
                $scenario = random_int(0, 2);

                $workers[] = [
                    'worker' => $worker,
                    'rol' => $rol,
                    'scenario' => $scenario,
                    'isReplacement' => $isReplacement,
                ];

                if ($scenario === 1) {
                    $this->createCompletedChecklist($worker->id, $fecha, $rol, 'presencial');
                } elseif ($scenario === 2) {
                    $this->createCompletedVirtual($worker->id, $fecha, $rol);
                }
            }

            // Run absence detection
            $result = $this->attendanceService->detectarAusencias($fecha);

            // Verify each worker
            foreach ($workers as $ws) {
                $worker = $ws['worker'];
                $scenario = $ws['scenario'];

                $hasAttendance = $this->attendanceService->tieneAsistencia($worker->id, $fecha);

                if ($scenario === 0) {
                    // No checklist → absent
                    $this->assertFalse(
                        $hasAttendance,
                        "Iteration {$i}: Worker without checklist should NOT have attendance"
                    );

                    // Should have ajuste -40000
                    $ajuste = AjusteSueldo::where('personal_id', $worker->id)
                        ->where('monto', -40000)
                        ->first();
                    $this->assertNotNull(
                        $ajuste,
                        "Iteration {$i}: Absent worker should have ajuste -40000"
                    );
                    $this->assertEquals(-40000, $ajuste->monto);
                } else {
                    // Completed presencial or virtual → present
                    $this->assertTrue(
                        $hasAttendance,
                        "Iteration {$i}: Worker with completed checklist (scenario={$scenario}) should have attendance"
                    );

                    // Should NOT have ajuste for inasistencia
                    $ajuste = AjusteSueldo::where('personal_id', $worker->id)
                        ->where('concepto', 'like', '%Inasistencia%')
                        ->first();
                    $this->assertNull(
                        $ajuste,
                        "Iteration {$i}: Present worker should NOT have inasistencia ajuste"
                    );
                }
            }
        }
    }
}
