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
 * Feature: mi3-worker-dashboard-v2, Property 2: Validación de monto de préstamo
 *
 * Validates: Requirements 3.4, 10.2
 *
 * Property: For any loan request with a given amount and a worker with a known base salary,
 * the request must be accepted if and only if the amount is > 0 and <= base salary.
 * Amounts outside that range must be rejected.
 */
class LoanAmountValidationPropertyTest extends TestCase
{
    use RefreshDatabase;

    private LoanService $loanService;
    private NotificationService $notificationServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationServiceMock = Mockery::mock(NotificationService::class);
        $this->notificationServiceMock->shouldReceive('crear')->andReturn(
            new \App\Models\NotificacionMi3()
        );

        $this->loanService = new LoanService($this->notificationServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper: create a worker with a specific base salary for a given role.
     */
    private function createWorkerWithSalary(string $role, float $sueldoBase): Personal
    {
        return Personal::create([
            'nombre' => 'Test Worker',
            'rol' => $role,
            'activo' => 1,
            'sueldo_base_cajero' => $role === 'cajero' ? $sueldoBase : 0,
            'sueldo_base_planchero' => $role === 'planchero' ? $sueldoBase : 0,
            'sueldo_base_admin' => $role === 'administrador' ? $sueldoBase : 0,
            'sueldo_base_seguridad' => $role === 'seguridad' ? $sueldoBase : 0,
        ]);
    }

    /**
     * Property 2: Valid amounts (> 0 and <= sueldo base) are accepted.
     *
     * **Validates: Requirements 3.4, 10.2**
     *
     * @test
     */
    public function valid_amounts_are_accepted_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $roles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        // Ensure 'prestamo' category exists
        AjusteCategoria::create([
            'nombre' => 'Cuota Préstamo',
            'slug' => 'prestamo',
            'icono' => '💰',
        ]);

        for ($i = 0; $i < 100; $i++) {
            $role = $faker->randomElement($roles);
            $sueldoBase = $faker->numberBetween(100000, 1000000);
            $personal = $this->createWorkerWithSalary($role, $sueldoBase);

            // Generate a valid amount: > 0 and <= sueldo base
            $monto = $faker->randomFloat(0, 1, $sueldoBase);
            $cuotas = $faker->numberBetween(1, 3);

            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                $monto,
                $cuotas,
                'Test motivo'
            );

            $this->assertInstanceOf(Prestamo::class, $prestamo);
            $this->assertEquals('pendiente', $prestamo->estado);
            $this->assertEquals($monto, $prestamo->monto_solicitado);
            $this->assertEquals($personal->id, $prestamo->personal_id);

            // Clean up for next iteration (allow new loan)
            $prestamo->delete();
        }
    }

    /**
     * Property 2: Amounts <= 0 are rejected.
     *
     * **Validates: Requirements 3.4, 10.2**
     *
     * @test
     */
    public function zero_or_negative_amounts_are_rejected_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $roles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            $role = $faker->randomElement($roles);
            $sueldoBase = $faker->numberBetween(100000, 1000000);
            $personal = $this->createWorkerWithSalary($role, $sueldoBase);

            // Generate an invalid amount: <= 0
            $monto = $faker->randomFloat(0, -1000000, 0);
            $cuotas = $faker->numberBetween(1, 3);

            try {
                $this->loanService->solicitarPrestamo(
                    $personal->id,
                    $monto,
                    $cuotas,
                    'Test motivo'
                );
                $this->fail("Expected InvalidArgumentException for amount {$monto} with sueldo base {$sueldoBase}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('El monto debe ser entre', $e->getMessage());
            }
        }
    }

    /**
     * Property 2: Amounts exceeding sueldo base are rejected.
     *
     * **Validates: Requirements 3.4, 10.2**
     *
     * @test
     */
    public function amounts_exceeding_sueldo_base_are_rejected_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $roles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            $role = $faker->randomElement($roles);
            $sueldoBase = $faker->numberBetween(100000, 1000000);
            $personal = $this->createWorkerWithSalary($role, $sueldoBase);

            // Generate an invalid amount: > sueldo base
            $monto = $sueldoBase + $faker->numberBetween(1, 500000);
            $cuotas = $faker->numberBetween(1, 3);

            try {
                $this->loanService->solicitarPrestamo(
                    $personal->id,
                    $monto,
                    $cuotas,
                    'Test motivo'
                );
                $this->fail("Expected InvalidArgumentException for amount {$monto} exceeding sueldo base {$sueldoBase}");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('El monto debe ser entre', $e->getMessage());
            }
        }
    }

    /**
     * Property 2: Boundary test — amount exactly equal to sueldo base is accepted.
     *
     * **Validates: Requirements 3.4, 10.2**
     *
     * @test
     */
    public function amount_equal_to_sueldo_base_is_accepted_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $roles = ['cajero', 'planchero', 'administrador', 'seguridad'];

        AjusteCategoria::firstOrCreate(
            ['slug' => 'prestamo'],
            ['nombre' => 'Cuota Préstamo', 'icono' => '💰']
        );

        for ($i = 0; $i < 100; $i++) {
            $role = $faker->randomElement($roles);
            $sueldoBase = $faker->numberBetween(100000, 1000000);
            $personal = $this->createWorkerWithSalary($role, $sueldoBase);

            // Amount exactly equal to sueldo base — should be accepted
            $cuotas = $faker->numberBetween(1, 3);

            $prestamo = $this->loanService->solicitarPrestamo(
                $personal->id,
                $sueldoBase,
                $cuotas,
                'Test motivo'
            );

            $this->assertInstanceOf(Prestamo::class, $prestamo);
            $this->assertEquals('pendiente', $prestamo->estado);
            $this->assertEquals($sueldoBase, $prestamo->monto_solicitado);

            // Clean up for next iteration
            $prestamo->delete();
        }
    }
}
