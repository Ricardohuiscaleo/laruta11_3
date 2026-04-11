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

// Feature: checklist-v2-asistencia, Property 4: Progreso y completado de checklist

/**
 * Property 4: Progreso y completado de checklist
 *
 * For any checklist with N items and K completed, percentage = (K/N)*100
 * and status = completed iff K = N.
 *
 * **Validates: Requirements 2.2, 2.3**
 */
class ProgressCompletionPropertyTest extends TestCase
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

    private function createWorker(): Personal
    {
        return Personal::create([
            'nombre' => 'Worker ' . uniqid(),
            'rol' => 'cajero',
            'activo' => 1,
            'sueldo_base_cajero' => 0,
            'sueldo_base_planchero' => 0,
            'sueldo_base_admin' => 0,
            'sueldo_base_seguridad' => 0,
        ]);
    }

    /**
     * Property 4: Progress percentage and completion status are correct.
     *
     * **Validates: Requirements 2.2, 2.3**
     *
     * @test
     */
    public function progress_and_status_correct_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $worker = $this->createWorker();
            $totalItems = random_int(1, 10);
            $itemsToComplete = random_int(0, $totalItems);

            $checklist = Checklist::create([
                'type' => 'apertura',
                'scheduled_date' => now()->format('Y-m-d'),
                'scheduled_time' => '18:00:00',
                'status' => 'pending',
                'personal_id' => $worker->id,
                'user_name' => $worker->nombre,
                'rol' => 'cajero',
                'checklist_mode' => 'presencial',
                'total_items' => $totalItems,
                'completed_items' => 0,
                'completion_percentage' => 0,
            ]);

            $itemIds = [];
            for ($j = 1; $j <= $totalItems; $j++) {
                $item = ChecklistItem::create([
                    'checklist_id' => $checklist->id,
                    'item_order' => $j,
                    'description' => "Item {$j}",
                    'requires_photo' => false,
                    'is_completed' => false,
                ]);
                $itemIds[] = $item->id;
            }

            for ($k = 0; $k < $itemsToComplete; $k++) {
                $this->service->marcarItemCompletado($itemIds[$k], $worker->id);
            }

            $checklist->refresh();

            $expectedPercentage = round(($itemsToComplete / $totalItems) * 100, 2);

            $this->assertEquals(
                $expectedPercentage,
                $checklist->completion_percentage,
                "Iteration {$i}: Expected {$expectedPercentage}%, got {$checklist->completion_percentage}%"
            );

            $this->assertEquals($itemsToComplete, $checklist->completed_items);

            if ($itemsToComplete === $totalItems && $itemsToComplete > 0) {
                $this->service->completarChecklist($checklist->id, $worker->id);
                $checklist->refresh();
                $this->assertEquals('completed', $checklist->status, "Iteration {$i}: All items done → completed");
            } elseif ($itemsToComplete > 0) {
                $this->assertEquals('active', $checklist->status, "Iteration {$i}: Some items done → active");
            } else {
                $this->assertEquals('pending', $checklist->status, "Iteration {$i}: No items done → pending");
            }
        }
    }
}
