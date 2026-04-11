<?php

namespace Tests\Unit\LoanService;

use App\Models\Personal;
use App\Models\Prestamo;
use App\Services\Loan\LoanService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 10: Préstamos ordenados por fecha descendente
 *
 * **Validates: Requirement 7.4**
 *
 * Property: For any list of loans returned by getPrestamosPorPersonal,
 * each loan must have a created_at >= the next loan's created_at (descending order).
 */
class LoansOrderedByDatePropertyTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loanService;

    protected function setUp(): void
    {
        parent::setUp();

        $notificationServiceMock = Mockery::mock(NotificationService::class);
        $notificationServiceMock->shouldReceive('crear')->andReturn(
            new \App\Models\NotificacionMi3()
        );

        $this->loanService = new LoanService($notificationServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: create a worker.
     */
    private function createWorker(): Personal
    {
        return Personal::create([
            'nombre' => 'Test Worker',
            'rol' => 'cajero',
            'activo' => 1,
            'sueldo_base_cajero' => 300000,
            'sueldo_base_planchero' => 0,
            'sueldo_base_admin' => 0,
            'sueldo_base_seguridad' => 0,
        ]);
    }

    /**
     * Property 10: Loans are returned in descending created_at order for 100 random scenarios.
     *
     * **Validates: Requirement 7.4**
     *
     * @test
     */
    public function loans_are_ordered_by_created_at_descending_for_100_random_scenarios(): void
    {
        $faker = Faker::create();
        $estados = ['pendiente', 'aprobado', 'rechazado', 'pagado', 'cancelado'];

        for ($i = 0; $i < 100; $i++) {
            $personal = $this->createWorker();

            // Generate between 2 and 8 loans with random dates
            $numLoans = $faker->numberBetween(2, 8);
            $dates = [];

            for ($j = 0; $j < $numLoans; $j++) {
                // Random date within the last 365 days
                $date = Carbon::now()->subDays($faker->numberBetween(0, 365))
                    ->setHour($faker->numberBetween(0, 23))
                    ->setMinute($faker->numberBetween(0, 59))
                    ->setSecond($faker->numberBetween(0, 59));

                $dates[] = $date;

                Prestamo::create([
                    'personal_id' => $personal->id,
                    'monto_solicitado' => $faker->numberBetween(10000, 300000),
                    'cuotas' => $faker->numberBetween(1, 3),
                    'cuotas_pagadas' => 0,
                    'estado' => $faker->randomElement($estados),
                    'created_at' => $date,
                    'updated_at' => $date,
                ]);
            }

            // Fetch loans via the service method
            $result = $this->loanService->getPrestamosPorPersonal($personal->id);

            // Verify count
            $this->assertCount(
                $numLoans,
                $result,
                "Iteration {$i}: expected {$numLoans} loans, got {$result->count()}"
            );

            // Verify descending order by created_at
            $resultArray = $result->values()->all();
            for ($k = 0; $k < count($resultArray) - 1; $k++) {
                $current = Carbon::parse($resultArray[$k]->created_at);
                $next = Carbon::parse($resultArray[$k + 1]->created_at);

                $this->assertTrue(
                    $current->gte($next),
                    "Iteration {$i}: loan at index {$k} (created_at={$current}) should be >= loan at index " . ($k + 1) . " (created_at={$next})"
                );
            }

            // Clean up for next iteration
            Prestamo::where('personal_id', $personal->id)->delete();
            $personal->delete();
        }
    }

    /**
     * Property 10: Empty loan list is valid (edge case).
     *
     * **Validates: Requirement 7.4**
     *
     * @test
     */
    public function empty_loan_list_is_valid_ordered(): void
    {
        $personal = $this->createWorker();

        $result = $this->loanService->getPrestamosPorPersonal($personal->id);

        $this->assertCount(0, $result);
    }

    /**
     * Property 10: Single loan is trivially ordered.
     *
     * **Validates: Requirement 7.4**
     *
     * @test
     */
    public function single_loan_is_trivially_ordered(): void
    {
        $faker = Faker::create();
        $personal = $this->createWorker();

        Prestamo::create([
            'personal_id' => $personal->id,
            'monto_solicitado' => $faker->numberBetween(10000, 300000),
            'cuotas' => 1,
            'cuotas_pagadas' => 0,
            'estado' => 'pendiente',
        ]);

        $result = $this->loanService->getPrestamosPorPersonal($personal->id);

        $this->assertCount(1, $result);
    }
}
