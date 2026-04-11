<?php

namespace Tests\Unit\LoanService;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
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
 * Feature: mi3-worker-dashboard-v2, Property 5: Auto-descuento mensual procesa préstamos correctamente
 *
 * **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
 *
 * Property: For any set of loans with status 'aprobado' and pending installments
 * (cuotas_pagadas < cuotas) whose fecha_inicio_descuento <= current month, the
 * deduction process must: (a) create a negative adjustment with amount = round(monto_aprobado / cuotas),
 * (b) increment cuotas_pagadas by 1, and (c) change status to 'pagado' when cuotas_pagadas reaches cuotas.
 */
class LoanAutoDeductPropertyTest extends TestCase
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
     * Property 5(a): Auto-deduct creates negative adjustments with correct amount.
     *
     * For each processed loan, a negative salary adjustment must be created with
     * monto = -round(monto_aprobado / cuotas).
     *
     * **Validates: Requirements 5.1, 5.2**
     *
     * @test
     */
    public function auto_deduct_creates_negative_adjustments_with_correct_amount_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');

        for ($i = 0; $i < 100; $i++) {
            // Generate random loan parameters
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);
            $montoAprobado = $faker->numberBetween(50000, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

            // fecha_inicio_descuento in the past so it's eligible
            $fechaInicio = Carbon::now()->subMonths($faker->numberBetween(1, 6))->startOfMonth();

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $montoAprobado,
                'monto_aprobado' => $montoAprobado,
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'estado' => 'aprobado',
                'aprobado_por' => $personal->id,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicio->format('Y-m-d'),
            ]);

            $adjustmentCountBefore = AjusteSueldo::where('personal_id', $personal->id)->count();

            $this->loanService->procesarDescuentosMensuales();

            // Verify a negative adjustment was created
            $newAdjustment = AjusteSueldo::where('personal_id', $personal->id)
                ->where('categoria_id', $categoriaId)
                ->latest('id')
                ->first();

            $this->assertNotNull(
                $newAdjustment,
                "Iteration {$i}: Negative adjustment should be created for loan #{$prestamo->id}"
            );

            $expectedMonto = -1 * (int) round($montoAprobado / $cuotas);

            $this->assertEquals(
                $expectedMonto,
                (int) $newAdjustment->monto,
                "Iteration {$i}: Adjustment monto should be {$expectedMonto} for loan #{$prestamo->id} (monto_aprobado={$montoAprobado}, cuotas={$cuotas})"
            );

            // Clean up for next iteration
            AjusteSueldo::where('personal_id', $personal->id)->delete();
            $prestamo->delete();
        }
    }

    /**
     * Property 5(b): Auto-deduct increments cuotas_pagadas by 1.
     *
     * After processing, each eligible loan's cuotas_pagadas must be incremented by exactly 1.
     *
     * **Validates: Requirements 5.1, 5.3**
     *
     * @test
     */
    public function auto_deduct_increments_cuotas_pagadas_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);
            $montoAprobado = $faker->numberBetween(50000, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

            $fechaInicio = Carbon::now()->subMonths($faker->numberBetween(1, 6))->startOfMonth();

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $montoAprobado,
                'monto_aprobado' => $montoAprobado,
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'estado' => 'aprobado',
                'aprobado_por' => $personal->id,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicio->format('Y-m-d'),
            ]);

            $this->loanService->procesarDescuentosMensuales();

            $prestamo->refresh();

            $this->assertEquals(
                $cuotasPagadas + 1,
                $prestamo->cuotas_pagadas,
                "Iteration {$i}: cuotas_pagadas should be " . ($cuotasPagadas + 1) . " (was {$cuotasPagadas}) for loan #{$prestamo->id}"
            );

            // Clean up
            AjusteSueldo::where('personal_id', $personal->id)->delete();
            $prestamo->delete();
        }
    }

    /**
     * Property 5(c): Auto-deduct changes status to 'pagado' when all installments are paid.
     *
     * When cuotas_pagadas reaches cuotas after processing, the loan status must change to 'pagado'.
     * When cuotas_pagadas < cuotas, the status must remain 'aprobado'.
     *
     * **Validates: Requirements 5.1, 5.4**
     *
     * @test
     */
    public function auto_deduct_changes_status_to_pagado_when_complete_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);
            $montoAprobado = $faker->numberBetween(50000, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);

            // Randomly decide if this will be the last installment
            $isLastInstallment = $faker->boolean(50);
            $cuotasPagadas = $isLastInstallment ? $cuotas - 1 : $faker->numberBetween(0, max(0, $cuotas - 2));

            $fechaInicio = Carbon::now()->subMonths($faker->numberBetween(1, 6))->startOfMonth();

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $montoAprobado,
                'monto_aprobado' => $montoAprobado,
                'cuotas' => $cuotas,
                'cuotas_pagadas' => $cuotasPagadas,
                'estado' => 'aprobado',
                'aprobado_por' => $personal->id,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicio->format('Y-m-d'),
            ]);

            $this->loanService->procesarDescuentosMensuales();

            $prestamo->refresh();

            $expectedStatus = ($cuotasPagadas + 1 >= $cuotas) ? 'pagado' : 'aprobado';

            $this->assertEquals(
                $expectedStatus,
                $prestamo->estado,
                "Iteration {$i}: Loan #{$prestamo->id} status should be '{$expectedStatus}' (cuotas={$cuotas}, cuotas_pagadas was {$cuotasPagadas}, now {$prestamo->cuotas_pagadas})"
            );

            // Clean up
            AjusteSueldo::where('personal_id', $personal->id)->delete();
            $prestamo->delete();
        }
    }

    /**
     * Property 5: Loans with fecha_inicio_descuento in the future are NOT processed.
     *
     * **Validates: Requirements 5.1**
     *
     * @test
     */
    public function auto_deduct_skips_loans_with_future_start_date_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);
            $montoAprobado = $faker->numberBetween(50000, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);

            // fecha_inicio_descuento in the future
            $fechaInicio = Carbon::now()->addMonths($faker->numberBetween(1, 6))->startOfMonth();

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $montoAprobado,
                'monto_aprobado' => $montoAprobado,
                'cuotas' => $cuotas,
                'cuotas_pagadas' => 0,
                'estado' => 'aprobado',
                'aprobado_por' => $personal->id,
                'fecha_aprobacion' => now(),
                'fecha_inicio_descuento' => $fechaInicio->format('Y-m-d'),
            ]);

            $this->loanService->procesarDescuentosMensuales();

            $prestamo->refresh();

            // Should NOT be processed
            $this->assertEquals(
                0,
                $prestamo->cuotas_pagadas,
                "Iteration {$i}: Loan #{$prestamo->id} with future start date should not be processed"
            );

            $adjustmentCount = AjusteSueldo::where('personal_id', $personal->id)->count();
            $this->assertEquals(
                0,
                $adjustmentCount,
                "Iteration {$i}: No adjustment should be created for loan with future start date"
            );

            // Clean up
            $prestamo->delete();
        }
    }

    /**
     * Property 5: Multiple loans from different workers are all processed correctly.
     *
     * **Validates: Requirements 5.1, 5.2, 5.3, 5.4**
     *
     * @test
     */
    public function auto_deduct_processes_multiple_loans_correctly_for_50_random_batches(): void
    {
        $faker = Faker::create();
        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');

        for ($batch = 0; $batch < 50; $batch++) {
            $loanCount = $faker->numberBetween(2, 5);
            $loans = [];

            for ($j = 0; $j < $loanCount; $j++) {
                $personal = $this->createWorker($faker->numberBetween(200000, 1000000));
                $montoAprobado = $faker->numberBetween(50000, 500000);
                $cuotas = $faker->numberBetween(1, 3);
                $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

                $fechaInicio = Carbon::now()->subMonths($faker->numberBetween(1, 3))->startOfMonth();

                $prestamo = Prestamo::create([
                    'personal_id' => $personal->id,
                    'monto_solicitado' => $montoAprobado,
                    'monto_aprobado' => $montoAprobado,
                    'cuotas' => $cuotas,
                    'cuotas_pagadas' => $cuotasPagadas,
                    'estado' => 'aprobado',
                    'aprobado_por' => $personal->id,
                    'fecha_aprobacion' => now(),
                    'fecha_inicio_descuento' => $fechaInicio->format('Y-m-d'),
                ]);

                $loans[] = [
                    'prestamo' => $prestamo,
                    'personal' => $personal,
                    'monto_aprobado' => $montoAprobado,
                    'cuotas' => $cuotas,
                    'cuotas_pagadas_before' => $cuotasPagadas,
                ];
            }

            $result = $this->loanService->procesarDescuentosMensuales();

            // Verify each loan was processed
            $this->assertCount(
                $loanCount,
                $result['resultados'],
                "Batch {$batch}: All {$loanCount} loans should be processed"
            );

            $this->assertEmpty(
                $result['errores'],
                "Batch {$batch}: No errors should occur"
            );

            foreach ($loans as $loanData) {
                $loanData['prestamo']->refresh();

                $expectedCuotasPagadas = $loanData['cuotas_pagadas_before'] + 1;
                $this->assertEquals(
                    $expectedCuotasPagadas,
                    $loanData['prestamo']->cuotas_pagadas,
                    "Batch {$batch}: Loan #{$loanData['prestamo']->id} cuotas_pagadas should be {$expectedCuotasPagadas}"
                );

                // Clean up
                AjusteSueldo::where('personal_id', $loanData['personal']->id)->delete();
                $loanData['prestamo']->delete();
            }
        }
    }
}
