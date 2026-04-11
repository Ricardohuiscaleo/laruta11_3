<?php

namespace Tests\Unit\LoanService;

use App\Models\AjusteCategoria;
use App\Models\AjusteSueldo;
use App\Models\Personal;
use App\Models\Prestamo;
use App\Services\Loan\LoanService;
use App\Services\Notification\NotificationService;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 4: Aprobación de préstamo crea registros correctos
 *
 * Validates: Requirements 4.2, 4.3, 10.4
 *
 * Property: For any pending loan that is approved with a given monto_aprobado,
 * the system must: (a) update status to 'aprobado', (b) record fecha_aprobacion
 * and aprobado_por, and (c) create a positive adjustment in ajustes_sueldo with
 * category 'prestamo' and amount equal to monto_aprobado.
 */
class LoanApprovalCreatesRecordsPropertyTest extends TestCase
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

    private function createAdmin(): Personal
    {
        return Personal::create([
            'nombre' => 'Test Admin',
            'rol' => 'administrador',
            'activo' => 1,
            'sueldo_base_cajero' => 0,
            'sueldo_base_planchero' => 0,
            'sueldo_base_admin' => 500000,
            'sueldo_base_seguridad' => 0,
        ]);
    }

    /**
     * Property 4: Approving a pending loan updates status to 'aprobado'.
     *
     * **Validates: Requirements 4.2, 4.3, 10.4**
     *
     * @test
     */
    public function approval_sets_correct_status_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $admin = $this->createAdmin();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $montoSolicitado = $faker->numberBetween(10000, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $montoSolicitado,
                'cuotas' => $cuotas,
                'estado' => 'pendiente',
            ]);

            // Approve with a random amount (can differ from requested)
            $montoAprobado = $faker->numberBetween(10000, $sueldoBase);

            $this->loanService->aprobar(
                $prestamo,
                $admin->id,
                $montoAprobado
            );

            $prestamo->refresh();

            // (a) Status must be 'aprobado'
            $this->assertEquals(
                'aprobado',
                $prestamo->estado,
                "Loan {$prestamo->id} should be 'aprobado' after approval"
            );

            // (b) monto_aprobado must match
            $this->assertEquals(
                $montoAprobado,
                $prestamo->monto_aprobado,
                "Loan {$prestamo->id} monto_aprobado should be {$montoAprobado}"
            );
        }
    }

    /**
     * Property 4: Approving a pending loan records fecha_aprobacion and aprobado_por.
     *
     * **Validates: Requirements 4.2, 4.3, 10.4**
     *
     * @test
     */
    public function approval_records_date_and_approver_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $admin = $this->createAdmin();

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'cuotas' => $faker->numberBetween(1, 3),
                'estado' => 'pendiente',
            ]);

            $montoAprobado = $faker->numberBetween(10000, $sueldoBase);
            $beforeApproval = now();

            $this->loanService->aprobar(
                $prestamo,
                $admin->id,
                $montoAprobado
            );

            $prestamo->refresh();

            // (b) aprobado_por must be the admin's ID
            $this->assertEquals(
                $admin->id,
                $prestamo->aprobado_por,
                "Loan {$prestamo->id} aprobado_por should be admin {$admin->id}"
            );

            // (b) fecha_aprobacion must be set and not null
            $this->assertNotNull(
                $prestamo->fecha_aprobacion,
                "Loan {$prestamo->id} fecha_aprobacion should not be null"
            );

            // fecha_aprobacion should be >= the time before approval
            $this->assertTrue(
                $prestamo->fecha_aprobacion->greaterThanOrEqualTo($beforeApproval->subSecond()),
                "Loan {$prestamo->id} fecha_aprobacion should be recent"
            );
        }
    }

    /**
     * Property 4: Approving a pending loan creates a positive salary adjustment.
     *
     * **Validates: Requirements 4.2, 4.3, 10.4**
     *
     * @test
     */
    public function approval_creates_positive_salary_adjustment_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $admin = $this->createAdmin();
        $categoriaId = AjusteCategoria::where('slug', 'prestamo')->value('id');

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'cuotas' => $faker->numberBetween(1, 3),
                'estado' => 'pendiente',
            ]);

            $montoAprobado = $faker->numberBetween(10000, $sueldoBase);

            // Count adjustments before approval
            $adjustmentCountBefore = AjusteSueldo::where('personal_id', $personal->id)->count();

            $this->loanService->aprobar(
                $prestamo,
                $admin->id,
                $montoAprobado
            );

            // (c) A positive salary adjustment must be created
            $adjustments = AjusteSueldo::where('personal_id', $personal->id)
                ->where('categoria_id', $categoriaId)
                ->get();

            $this->assertCount(
                $adjustmentCountBefore + 1,
                AjusteSueldo::where('personal_id', $personal->id)->get(),
                "One new adjustment should be created for loan {$prestamo->id}"
            );

            // Find the new adjustment
            $newAdjustment = AjusteSueldo::where('personal_id', $personal->id)
                ->where('categoria_id', $categoriaId)
                ->latest('id')
                ->first();

            $this->assertNotNull($newAdjustment, "Salary adjustment should exist");

            // Amount must be positive and equal to monto_aprobado
            $this->assertEquals(
                $montoAprobado,
                $newAdjustment->monto,
                "Adjustment amount should equal monto_aprobado ({$montoAprobado})"
            );

            $this->assertGreaterThan(
                0,
                $newAdjustment->monto,
                "Adjustment amount should be positive"
            );

            // Category must be 'prestamo'
            $this->assertEquals(
                $categoriaId,
                $newAdjustment->categoria_id,
                "Adjustment category should be 'prestamo'"
            );
        }
    }

    /**
     * Property 4: Approving a non-pending loan throws exception.
     *
     * **Validates: Requirements 4.2, 4.3, 10.4**
     *
     * @test
     */
    public function approving_non_pending_loan_throws_exception_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $admin = $this->createAdmin();
        $nonPendingStates = ['aprobado', 'rechazado', 'pagado', 'cancelado'];

        for ($i = 0; $i < 100; $i++) {
            $sueldoBase = $faker->numberBetween(200000, 1000000);
            $personal = $this->createWorker($sueldoBase);
            $state = $faker->randomElement($nonPendingStates);

            $prestamo = Prestamo::create([
                'personal_id' => $personal->id,
                'monto_solicitado' => $faker->numberBetween(10000, $sueldoBase),
                'monto_aprobado' => $state === 'aprobado' || $state === 'pagado'
                    ? $faker->numberBetween(10000, $sueldoBase)
                    : null,
                'cuotas' => $faker->numberBetween(1, 3),
                'estado' => $state,
            ]);

            $montoAprobado = $faker->numberBetween(10000, $sueldoBase);

            try {
                $this->loanService->aprobar(
                    $prestamo,
                    $admin->id,
                    $montoAprobado
                );
                $this->fail(
                    "Expected InvalidArgumentException for approving loan in state '{$state}'"
                );
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString(
                    'Solo se pueden aprobar préstamos pendientes',
                    $e->getMessage()
                );
            }
        }
    }
}
