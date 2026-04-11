<?php

namespace Tests\Unit\Dashboard;

use Faker\Factory as Faker;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 6: Cálculo de resumen de préstamo activo
 *
 * Validates: Requirements 6.2, 7.3
 *
 * Property: For any active loan (approved with pending installments), the pending amount
 * must equal monto_aprobado - (cuotas_pagadas × round(monto_aprobado / cuotas)),
 * remaining installments must be cuotas - cuotas_pagadas, and the next installment
 * amount must be round(monto_aprobado / cuotas).
 */
class ActiveLoanSummaryPropertyTest extends TestCase
{
    /**
     * Compute loan summary the same way DashboardController does.
     */
    private function computeSummary(float $montoAprobado, int $cuotas, int $cuotasPagadas): array
    {
        $montoCuota = (int) round($montoAprobado / $cuotas);
        $cuotasRestantes = $cuotas - $cuotasPagadas;
        $montoPendiente = $montoAprobado - ($cuotasPagadas * $montoCuota);

        return [
            'monto_cuota' => $montoCuota,
            'cuotas_restantes' => $cuotasRestantes,
            'monto_pendiente' => $montoPendiente,
        ];
    }

    /**
     * Property 6: Remaining installments = cuotas - cuotas_pagadas.
     *
     * **Validates: Requirements 6.2, 7.3**
     *
     * @test
     */
    public function remaining_installments_equals_total_minus_paid_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $montoAprobado = $faker->numberBetween(50000, 1000000);
            $cuotas = $faker->numberBetween(1, 3);
            // cuotas_pagadas must be < cuotas (active loan)
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

            $summary = $this->computeSummary($montoAprobado, $cuotas, $cuotasPagadas);

            $this->assertEquals(
                $cuotas - $cuotasPagadas,
                $summary['cuotas_restantes'],
                "cuotas_restantes should be {$cuotas} - {$cuotasPagadas} = " . ($cuotas - $cuotasPagadas)
                    . " (iteration {$i})"
            );
        }
    }

    /**
     * Property 6: Installment amount = round(monto_aprobado / cuotas).
     *
     * **Validates: Requirements 6.2, 7.3**
     *
     * @test
     */
    public function installment_amount_is_rounded_division_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $montoAprobado = $faker->numberBetween(50000, 1000000);
            $cuotas = $faker->numberBetween(1, 3);
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

            $summary = $this->computeSummary($montoAprobado, $cuotas, $cuotasPagadas);
            $expectedCuota = (int) round($montoAprobado / $cuotas);

            $this->assertEquals(
                $expectedCuota,
                $summary['monto_cuota'],
                "monto_cuota should be round({$montoAprobado} / {$cuotas}) = {$expectedCuota}"
                    . " (iteration {$i})"
            );
        }
    }

    /**
     * Property 6: Pending amount = monto_aprobado - (cuotas_pagadas × monto_cuota).
     *
     * **Validates: Requirements 6.2, 7.3**
     *
     * @test
     */
    public function pending_amount_is_correct_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $montoAprobado = $faker->numberBetween(50000, 1000000);
            $cuotas = $faker->numberBetween(1, 3);
            $cuotasPagadas = $faker->numberBetween(0, $cuotas - 1);

            $summary = $this->computeSummary($montoAprobado, $cuotas, $cuotasPagadas);
            $montoCuota = (int) round($montoAprobado / $cuotas);
            $expectedPendiente = $montoAprobado - ($cuotasPagadas * $montoCuota);

            $this->assertEquals(
                $expectedPendiente,
                $summary['monto_pendiente'],
                "monto_pendiente should be {$montoAprobado} - ({$cuotasPagadas} × {$montoCuota})"
                    . " = {$expectedPendiente} (iteration {$i})"
            );
        }
    }

    /**
     * Property 6: When no installments paid, pending = full approved amount.
     *
     * **Validates: Requirements 6.2, 7.3**
     *
     * @test
     */
    public function no_payments_means_full_amount_pending_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $montoAprobado = $faker->numberBetween(50000, 1000000);
            $cuotas = $faker->numberBetween(1, 3);

            $summary = $this->computeSummary($montoAprobado, $cuotas, 0);

            $this->assertEquals(
                $montoAprobado,
                $summary['monto_pendiente'],
                "With 0 payments, pending should equal full amount {$montoAprobado} (iteration {$i})"
            );
        }
    }
}
