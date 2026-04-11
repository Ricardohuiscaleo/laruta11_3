<?php

namespace Tests\Unit\LoanService;

use App\Models\AjusteCategoria;
use App\Models\Personal;
use App\Models\Prestamo;
use App\Services\Loan\LoanService;
use App\Services\Notification\NotificationService;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 3: Préstamo activo impide nueva solicitud
 *
 * Validates: Requirements 3.5, 10.2
 *
 * Property: For any worker who has a loan with status 'aprobado' and cuotas_pagadas < cuotas,
 * attempting to create a new loan request must be rejected. Workers without an active loan
 * (or with fully paid / rejected / cancelled loans) should be able to request a new loan.
 */
class ActiveLoanBlocksNewRequestPropertyTest extends TestCase
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

        AjusteCategoria::firstOrCreate(
            ['slug' => 'prestamo'],
            ['nombre' => 'Cuota Préstamo', 'icono' => '💰']
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createWorker(float $sueldoBase = 500000): Personal
    {
        return Personal::create([
            'nombre' => 'Test Worker',
            'rol' => 'cajero',
            'activo' => 1,
            'sueldo_base_cajero' => $sueldoBase,
            'sueldo_base_planchero' => 0,
            'sueldo_base_admin' => 0,
            'sueldo_base_seguridad' => 0,
        ]);
    }

    /**
     * Property 3: Active loan (aprobado with pending installments) blocks new request.
     *
     * **Validates: Requirements 3.5, 10.2**
     *
     * @test
     */
    public function active_loan_blocks_new_request_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $cuotas = $faker->numberBetween(1, 3);
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1); // Always less than cuotas

            // Create an active loan (aprobado with pending installments)
            Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'monto_aprobado' => $faker->numberBetween(10000, $sueldoBase),
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'estado' => 'aprobado',
            ]);

            // Attempting a new loan should fail
            $newMonto = $faker->numberBetween(1, $sueldoBase);
            $newCuotas = $faker->numberBetween(1, 3);

            try {
                $this->loanService->solicitarPrestamo(
                    $personal->id,
                    $newMonto,
                    $newCuotas,
                    'Test motivo'
                );
                $this->fail(
                    "Expected InvalidArgumentException: worker {$personal->id} has active loan " .
                    "(cuotas={$cuotas}, pagadas={$cuotasPagadas}) but new request was accepted"
                );
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('préstamo activo', $e->getMessage());
            }
        }
    }

    /**
     * Property 3: Non-blocking loan states allow new requests.
     *
     * Workers with loans in states that are NOT active (rechazado, pagado, cancelado,
     * or aprobado with all installments paid) should be able to request a new loan.
     *
     * **Validates: Requirements 3.5, 10.2**
     *
     * @test
     */
    public function non_active_loan_states_allow_new_request_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $nonBlockingStates = ['rechazado', 'pagado', 'cancelado'];

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $state = $faker->randomElement($nonBlockingStates);
            $cuotas = $faker->numberBetween(1, 3);

            // Create a loan in a non-blocking state
            Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'monto_aprobado' => $state === 'pagado' ? $faker->numberBetween(10000, $sueldoBase) : null,
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $state === 'pagado' ? $cuotas : 0,
                'estado' => $state,
            ]);

            // New loan request should succeed
            $newMonto = $faker->numberBetween(1, $sueldoBase);
            $newCuotas = $faker->numberBetween(1, 3);

            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                $newMonto,
                $newCuotas,
                'Test motivo'
            );

            $this->assertInstanceOf(Prestamo::class, $prestamo);
            $this->assertEquals('pendiente', $prestamo->estado);

            // Clean up the new loan so next iteration doesn't conflict
            $prestamo->delete();
        }
    }

    /**
     * Property 3: Fully paid approved loan (cuotas_pagadas == cuotas) does NOT block.
     *
     * **Validates: Requirements 3.5, 10.2**
     *
     * @test
     */
    public function fully_paid_approved_loan_does_not_block_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $cuotas = $faker->numberBetween(1, 3);

            // Create an approved loan that is fully paid (cuotas_pagadas == cuotas)
            Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'monto_aprobado' => $faker->numberBetween(10000, $sueldoBase),
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $cuotas, // Fully paid
                'estado' => 'aprobado',
            ]);

            // New loan request should succeed since all installments are paid
            $newMonto = $faker->numberBetween(1, $sueldoBase);
            $newCuotas = $faker->numberBetween(1, 3);

            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                $newMonto,
                $newCuotas,
                'Test motivo'
            );

            $this->assertInstanceOf(Prestamo::class, $prestamo);
            $this->assertEquals('pendiente', $prestamo->estado);

            // Clean up
            $prestamo->delete();
        }
    }
}
