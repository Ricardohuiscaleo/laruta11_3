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

// Feature: checklist-v2-asistencia, Property 5: Validación de foto obligatoria

/**
 * Property 5: Validación de foto obligatoria
 *
 * For any item with requires_photo=true and photo_url null/non-null,
 * the system rejects completion without photo and accepts with photo.
 *
 * **Validates: Requirement 2.6**
 */
class PhotoValidationPropertyTest extends TestCase
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
     * Property 5: Items with requires_photo=true are rejected without photo, accepted with photo.
     *
     * **Validates: Requirement 2.6**
     *
     * @test
     */
    public function photo_required_items_validated_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $worker = $this->createWorker();
            $hasPhoto = (bool) random_int(0, 1);

            $checklist = Checklist::create([
                'type' => 'apertura',
                'scheduled_date' => now()->format('Y-m-d'),
                'scheduled_time' => '18:00:00',
                'status' => 'pending',
                'personal_id' => $worker->id,
                'user_name' => $worker->nombre,
                'rol' => 'cajero',
                'checklist_mode' => 'presencial',
                'total_items' => 1,
                'completed_items' => 0,
                'completion_percentage' => 0,
            ]);

            $item = ChecklistItem::create([
                'checklist_id' => $checklist->id,
                'item_order' => 1,
                'description' => 'FOTO test',
                'requires_photo' => true,
                'photo_url' => $hasPhoto ? 'https://s3.amazonaws.com/test/photo_' . uniqid() . '.jpg' : null,
                'is_completed' => false,
            ]);

            if ($hasPhoto) {
                $result = $this->service->marcarItemCompletado($item->id, $worker->id);
                $this->assertTrue($result['item']->is_completed, "Iteration {$i}: Item with photo should be completed");
            } else {
                try {
                    $this->service->marcarItemCompletado($item->id, $worker->id);
                    $this->fail("Iteration {$i}: Expected exception for item without photo");
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringContainsString('foto', $e->getMessage());
                }
            }
        }
    }
}
