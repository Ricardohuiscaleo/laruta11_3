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

// Feature: checklist-v2-asistencia, Property 11: Filtrado por fecha retorna solo checklists correspondientes

/**
 * Property 11: Filtrado por fecha retorna solo checklists correspondientes
 *
 * For any date filter and set of checklists across multiple dates,
 * the filtered query returns only checklists whose scheduled_date matches the requested date.
 *
 * **Validates: Requirement 7.4**
 */
class DateFilterPropertyTest extends TestCase
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
     * Property 11: Date filter returns only checklists for the requested date.
     *
     * **Validates: Requirement 7.4**
     *
     * @test
     */
    public function date_filter_returns_only_matching_checklists_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $worker = $this->createWorker(['cajero', 'planchero'][random_int(0, 1)]);

            // Generate 3-7 distinct dates
            $numDates = random_int(3, 7);
            $baseDateOffset = random_int(1, 30);
            $dates = [];
            for ($d = 0; $d < $numDates; $d++) {
                $dates[] = now()->addDays($baseDateOffset + $d)->format('Y-m-d');
            }

            // Create checklists spread across dates
            $checklistsByDate = [];
            $numChecklists = random_int(4, 12);
            for ($j = 0; $j < $numChecklists; $j++) {
                $date = $dates[random_int(0, count($dates) - 1)];
                $type = ['apertura', 'cierre'][random_int(0, 1)];
                $status = ['pending', 'active', 'completed'][random_int(0, 2)];

                Checklist::create([
                    'type' => $type,
                    'scheduled_date' => $date,
                    'scheduled_time' => '18:00:00',
                    'status' => $status,
                    'personal_id' => $worker->id,
                    'user_name' => $worker->nombre,
                    'rol' => $worker->rol,
                    'checklist_mode' => 'presencial',
                    'total_items' => 2,
                    'completed_items' => 0,
                    'completion_percentage' => 0,
                ]);

                if (!isset($checklistsByDate[$date])) {
                    $checklistsByDate[$date] = 0;
                }
                $checklistsByDate[$date]++;
            }

            // Pick a random date to filter by
            $filterDate = $dates[random_int(0, count($dates) - 1)];
            $expectedCount = $checklistsByDate[$filterDate] ?? 0;

            // Use the admin method which filters by date
            $result = $this->service->getChecklistsAdmin($filterDate);

            // Verify count matches
            $this->assertEquals(
                $expectedCount,
                $result->count(),
                "Iteration {$i}: Expected {$expectedCount} checklists for date {$filterDate}, got {$result->count()}"
            );

            // Verify all returned checklists have the correct date
            foreach ($result as $cl) {
                $this->assertEquals(
                    $filterDate,
                    $cl->scheduled_date->format('Y-m-d'),
                    "Iteration {$i}: Returned checklist has date {$cl->scheduled_date->format('Y-m-d')} but filter was {$filterDate}"
                );
            }
        }
    }
}
