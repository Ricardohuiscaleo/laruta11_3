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

// Feature: checklist-v2-asistencia, Property 10: Correctitud del resumen mensual de asistencia

/**
 * Property 10: Correctitud del resumen mensual de asistencia
 *
 * For any worker and month, the attendance summary must satisfy:
 * dias_trabajados + inasistencias = total_turnos
 * monto_descuentos = inasistencias × 40000
 *
 * **Validates: Requirement 7.3**
 */
class MonthlySummaryPropertyTest extends TestCase
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

    private function seedInasistenciaCategory(): AjusteCategoria
    {
        return AjusteCategoria::create([
            'nombre' => 'Inasistencia',
            'slug' => 'inasistencia',
            'icono' => '❌',
        ]);
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
     * Property 10: dias_trabajados + inasistencias = total_turnos and monto_descuentos = inasistencias × 40000.
     *
     * **Validates: Requirement 7.3**
     *
     * @test
     */
    public function monthly_summary_invariants_hold_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();
            $categoria = $this->seedInasistenciaCategory();

            $rol = ['cajero', 'planchero'][random_int(0, 1)];
            $worker = $this->createWorker($rol);

            // Use a fixed month for testing
            $year = 2025;
            $month = random_int(1, 12);
            $mes = sprintf('%04d-%02d-01', $year, $month);
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

            // Generate random number of shifts (1 to min(15, daysInMonth))
            $maxShifts = min(15, $daysInMonth);
            $numShifts = random_int(1, $maxShifts);

            // Pick random unique days for shifts
            $allDays = range(1, $daysInMonth);
            shuffle($allDays);
            $shiftDays = array_slice($allDays, 0, $numShifts);
            sort($shiftDays);

            // Randomly decide which shifts have attendance
            $attendedDays = [];
            $virtualDays = [];
            $absentDays = [];

            foreach ($shiftDays as $day) {
                $fecha = sprintf('%04d-%02d-%02d', $year, $month, $day);

                Turno::create([
                    'personal_id' => $worker->id,
                    'fecha' => $fecha,
                    'tipo' => 'normal',
                ]);

                // 0=absent, 1=presencial, 2=virtual
                $scenario = random_int(0, 2);

                if ($scenario === 1) {
                    // Presencial attendance
                    Checklist::create([
                        'type' => 'apertura',
                        'scheduled_date' => $fecha,
                        'scheduled_time' => '18:00:00',
                        'status' => 'completed',
                        'personal_id' => $worker->id,
                        'user_name' => $worker->nombre,
                        'rol' => $rol,
                        'checklist_mode' => 'presencial',
                        'total_items' => 1,
                        'completed_items' => 1,
                        'completion_percentage' => 100,
                        'completed_at' => now(),
                    ]);
                    $attendedDays[] = $day;
                } elseif ($scenario === 2) {
                    // Virtual attendance
                    $checklist = Checklist::create([
                        'type' => 'apertura',
                        'scheduled_date' => $fecha,
                        'scheduled_time' => '18:00:00',
                        'status' => 'completed',
                        'personal_id' => $worker->id,
                        'user_name' => $worker->nombre,
                        'rol' => $rol,
                        'checklist_mode' => 'virtual',
                        'total_items' => 0,
                        'completed_items' => 0,
                        'completion_percentage' => 0,
                        'completed_at' => now(),
                    ]);
                    ChecklistVirtual::create([
                        'checklist_id' => $checklist->id,
                        'personal_id' => $worker->id,
                        'confirmation_text' => 'Confirmo...',
                        'improvement_idea' => 'Idea de mejora con más de 20 caracteres',
                        'completed_at' => now(),
                        'created_at' => now(),
                    ]);
                    $virtualDays[] = $day;
                } else {
                    // Absent — create ajuste_sueldo
                    AjusteSueldo::create([
                        'personal_id' => $worker->id,
                        'mes' => $mes,
                        'monto' => -40000,
                        'concepto' => "Inasistencia {$fecha}",
                        'categoria_id' => $categoria->id,
                        'notas' => 'Descuento automático',
                    ]);
                    $absentDays[] = $day;
                }
            }

            $expectedWorked = count($attendedDays) + count($virtualDays);
            $expectedAbsent = count($absentDays);
            $expectedDiscount = $expectedAbsent * 40000;

            $resumen = $this->service->getResumenAsistenciaMensual($worker->id, $mes);

            // Invariant 1: dias_trabajados + inasistencias = total_turnos
            $this->assertEquals(
                $resumen['total_turnos'],
                $resumen['dias_trabajados'] + $resumen['inasistencias'],
                "Iteration {$i}: dias_trabajados ({$resumen['dias_trabajados']}) + inasistencias ({$resumen['inasistencias']}) should equal total_turnos ({$resumen['total_turnos']})"
            );

            // Invariant 2: monto_descuentos = inasistencias × 40000
            $this->assertEquals(
                $expectedDiscount,
                $resumen['monto_descuentos'],
                "Iteration {$i}: monto_descuentos should be {$expectedDiscount}, got {$resumen['monto_descuentos']}"
            );

            // Verify individual counts
            $this->assertEquals($numShifts, $resumen['total_turnos'], "Iteration {$i}: total_turnos");
            $this->assertEquals($expectedWorked, $resumen['dias_trabajados'], "Iteration {$i}: dias_trabajados");
            $this->assertEquals($expectedAbsent, $resumen['inasistencias'], "Iteration {$i}: inasistencias");
            $this->assertEquals(count($virtualDays), $resumen['virtuales'], "Iteration {$i}: virtuales");
        }
    }
}
