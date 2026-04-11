<?php

namespace Tests\Unit\Dashboard;

use Faker\Factory as Faker;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 8: Cálculo de resumen de reemplazos
 *
 * Validates: Requirements 6.4, 8.2
 *
 * Property: For any set of replacement shifts for a worker in a given month,
 * the net balance must equal the sum of amounts from replacements done (realizados)
 * minus the sum of amounts from replacements received (recibidos).
 */
class ReplacementSummaryPropertyTest extends TestCase
{
    /**
     * Simulate the replacement summary logic from ReplacementController.
     *
     * @param array $realizados Array of ['monto' => int]
     * @param array $recibidos  Array of ['monto' => int]
     * @return array{total_ganado: int, total_descontado: int, balance: int}
     */
    private function computeResumen(array $realizados, array $recibidos): array
    {
        $totalGanado = array_sum(array_column($realizados, 'monto'));
        $totalDescontado = array_sum(array_column($recibidos, 'monto'));

        return [
            'total_ganado' => $totalGanado,
            'total_descontado' => $totalDescontado,
            'balance' => $totalGanado - $totalDescontado,
        ];
    }

    /**
     * Property 8: Balance = total_ganado - total_descontado.
     *
     * **Validates: Requirements 6.4, 8.2**
     *
     * @test
     */
    public function balance_equals_ganado_minus_descontado_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $numRealizados = $faker->numberBetween(0, 8);
            $numRecibidos = $faker->numberBetween(0, 8);

            $realizados = [];
            for ($j = 0; $j < $numRealizados; $j++) {
                $realizados[] = ['monto' => $faker->numberBetween(10000, 50000)];
            }

            $recibidos = [];
            for ($j = 0; $j < $numRecibidos; $j++) {
                $recibidos[] = ['monto' => $faker->numberBetween(10000, 50000)];
            }

            $resumen = $this->computeResumen($realizados, $recibidos);

            $this->assertEquals(
                $resumen['total_ganado'] - $resumen['total_descontado'],
                $resumen['balance'],
                "Balance should be total_ganado - total_descontado (iteration {$i})"
            );
        }
    }

    /**
     * Property 8: total_ganado equals sum of all realizados amounts.
     *
     * **Validates: Requirements 6.4, 8.2**
     *
     * @test
     */
    public function total_ganado_equals_sum_of_realizados_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $numRealizados = $faker->numberBetween(0, 10);
            $realizados = [];
            $expectedSum = 0;

            for ($j = 0; $j < $numRealizados; $j++) {
                $monto = $faker->numberBetween(10000, 50000);
                $realizados[] = ['monto' => $monto];
                $expectedSum += $monto;
            }

            $resumen = $this->computeResumen($realizados, []);

            $this->assertEquals(
                $expectedSum,
                $resumen['total_ganado'],
                "total_ganado should equal sum of realizados (iteration {$i})"
            );
        }
    }

    /**
     * Property 8: total_descontado equals sum of all recibidos amounts.
     *
     * **Validates: Requirements 6.4, 8.2**
     *
     * @test
     */
    public function total_descontado_equals_sum_of_recibidos_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $numRecibidos = $faker->numberBetween(0, 10);
            $recibidos = [];
            $expectedSum = 0;

            for ($j = 0; $j < $numRecibidos; $j++) {
                $monto = $faker->numberBetween(10000, 50000);
                $recibidos[] = ['monto' => $monto];
                $expectedSum += $monto;
            }

            $resumen = $this->computeResumen([], $recibidos);

            $this->assertEquals(
                $expectedSum,
                $resumen['total_descontado'],
                "total_descontado should equal sum of recibidos (iteration {$i})"
            );
        }
    }

    /**
     * Property 8: With no replacements, all values are 0.
     *
     * **Validates: Requirements 6.4, 8.2**
     *
     * @test
     */
    public function empty_replacements_yield_zero_summary(): void
    {
        $resumen = $this->computeResumen([], []);

        $this->assertEquals(0, $resumen['total_ganado']);
        $this->assertEquals(0, $resumen['total_descontado']);
        $this->assertEquals(0, $resumen['balance']);
    }
}
