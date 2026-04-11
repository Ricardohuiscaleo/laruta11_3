<?php

namespace Tests\Unit\ChecklistService;

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Services\Checklist\ChecklistService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 12: Ideas de mejora ordenadas por fecha descendente

/**
 * Property 12: Ideas de mejora ordenadas por fecha descendente
 *
 * For any set of completed virtual checklists, the ideas query returns them
 * ordered by completed_at in descending order.
 *
 * **Validates: Requirement 7.5**
 */
class IdeasOrderPropertyTest extends TestCase
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
     * Property 12: Ideas are returned in descending order by completed_at.
     *
     * **Validates: Requirement 7.5**
     *
     * @test
     */
    public function ideas_ordered_by_date_descending_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $numVirtuals = random_int(2, 8);
            $workers = [];
            for ($w = 0; $w < $numVirtuals; $w++) {
                $workers[] = $this->createWorker(['cajero', 'planchero'][random_int(0, 1)]);
            }

            // Create virtual checklists with random completion dates
            for ($j = 0; $j < $numVirtuals; $j++) {
                $worker = $workers[$j];
                $daysOffset = random_int(1, 90);
                $date = now()->subDays($daysOffset)->format('Y-m-d');

                $checklist = Checklist::create([
                    'type' => 'apertura',
                    'scheduled_date' => $date,
                    'scheduled_time' => '18:00:00',
                    'status' => 'completed',
                    'personal_id' => $worker->id,
                    'user_name' => $worker->nombre,
                    'rol' => $worker->rol,
                    'checklist_mode' => 'virtual',
                    'total_items' => 0,
                    'completed_items' => 0,
                    'completion_percentage' => 0,
                ]);

                // Random hour/minute for uniqueness
                $completedAt = Carbon::parse($date)->setHour(random_int(0, 23))->setMinute(random_int(0, 59))->setSecond(random_int(0, 59));

                ChecklistVirtual::create([
                    'checklist_id' => $checklist->id,
                    'personal_id' => $worker->id,
                    'confirmation_text' => 'Confirmación test',
                    'improvement_idea' => 'Idea de mejora de prueba número ' . ($j + 1) . ' con suficientes caracteres',
                    'completed_at' => $completedAt,
                    'created_at' => $completedAt->copy()->subHour(),
                ]);
            }

            $result = $this->service->getIdeasMejora();

            // Verify count
            $this->assertEquals(
                $numVirtuals,
                $result->count(),
                "Iteration {$i}: Expected {$numVirtuals} ideas, got {$result->count()}"
            );

            // Verify descending order by completed_at
            $previous = null;
            foreach ($result as $idea) {
                if ($previous !== null) {
                    $this->assertGreaterThanOrEqual(
                        $idea->completed_at->timestamp,
                        $previous->completed_at->timestamp,
                        "Iteration {$i}: Ideas not in descending order. {$previous->completed_at} should be >= {$idea->completed_at}"
                    );
                }
                $previous = $idea;
            }
        }
    }
}
