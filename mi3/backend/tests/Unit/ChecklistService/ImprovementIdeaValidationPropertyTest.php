<?php

namespace Tests\Unit\ChecklistService;

use App\Models\Checklist;
use App\Models\ChecklistVirtual;
use App\Models\Personal;
use App\Services\Checklist\ChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Feature: checklist-v2-asistencia, Property 9: Validación de idea de mejora en checklist virtual

/**
 * Property 9: Validación de idea de mejora en checklist virtual
 *
 * For any string of random length (0-100), the system rejects < 20 chars
 * and accepts >= 20 chars.
 *
 * **Validates: Requirement 5.3**
 */
class ImprovementIdeaValidationPropertyTest extends TestCase
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

    private function randomString(int $length): string
    {
        if ($length === 0) {
            return '';
        }
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $result;
    }

    /**
     * Property 9: Ideas < 20 chars rejected, >= 20 chars accepted.
     *
     * **Validates: Requirement 5.3**
     *
     * @test
     */
    public function improvement_idea_length_validated_for_100_random_inputs(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->cleanUp();

            $worker = $this->createWorker();

            $checklist = Checklist::create([
                'type' => 'apertura',
                'scheduled_date' => now()->format('Y-m-d'),
                'scheduled_time' => '18:00:00',
                'status' => 'pending',
                'personal_id' => $worker->id,
                'user_name' => $worker->nombre,
                'rol' => 'cajero',
                'checklist_mode' => 'virtual',
                'total_items' => 0,
                'completed_items' => 0,
                'completion_percentage' => 0,
            ]);

            $virtual = ChecklistVirtual::create([
                'checklist_id' => $checklist->id,
                'personal_id' => $worker->id,
                'created_at' => now(),
            ]);

            $length = random_int(0, 100);
            $idea = $this->randomString($length);

            if ($length < 20) {
                try {
                    $this->service->completarChecklistVirtual($virtual->id, $worker->id, $idea);
                    $this->fail("Iteration {$i}: Expected exception for idea with {$length} chars");
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringContainsString('20 caracteres', $e->getMessage());
                }
            } else {
                $result = $this->service->completarChecklistVirtual($virtual->id, $worker->id, $idea);
                $this->assertNotNull($result->completed_at, "Iteration {$i}: Virtual checklist should be completed");
                $this->assertEquals(trim($idea), $result->improvement_idea);
            }
        }
    }
}
