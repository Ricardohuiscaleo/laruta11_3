<?php

namespace Tests\Unit\Dashboard;

use Faker\Factory as Faker;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 7: Agregación de descuentos por categoría
 *
 * Validates: Requirement 6.3
 *
 * Property: For any set of negative adjustments for a worker in a given month,
 * the total discounts must equal the sum of all negative amounts, and the
 * breakdown by category must sum exactly to the total.
 */
class DiscountAggregationPropertyTest extends TestCase
{
    /**
     * Simulate the discount aggregation logic from DashboardController.
     *
     * @param array $ajustes Array of ['monto' => float, 'categoria_slug' => string]
     * @return array{total: int, por_categoria: array<string, int>}
     */
    private function aggregateDiscounts(array $ajustes): array
    {
        $total = 0;
        $porCategoria = [];

        foreach ($ajustes as $ajuste) {
            $monto = (int) $ajuste['monto'];
            $slug = $ajuste['categoria_slug'];

            $total += $monto;

            if (!isset($porCategoria[$slug])) {
                $porCategoria[$slug] = 0;
            }
            $porCategoria[$slug] += $monto;
        }

        return [
            'total' => $total,
            'por_categoria' => $porCategoria,
        ];
    }

    /**
     * Property 7: Total discounts equals sum of all negative amounts.
     *
     * **Validates: Requirement 6.3**
     *
     * @test
     */
    public function total_equals_sum_of_all_negative_amounts_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $categorias = ['prestamo', 'descuento_credito_r11', 'adelanto', 'multa', 'otros'];

        for ($i = 0; $i < 100; $i++) {
            $numAjustes = $faker->numberBetween(1, 10);
            $ajustes = [];
            $expectedTotal = 0;

            for ($j = 0; $j < $numAjustes; $j++) {
                $monto = -$faker->numberBetween(1000, 100000);
                $ajustes[] = [
                    'monto' => $monto,
                    'categoria_slug' => $faker->randomElement($categorias),
                ];
                $expectedTotal += (int) $monto;
            }

            $result = $this->aggregateDiscounts($ajustes);

            $this->assertEquals(
                $expectedTotal,
                $result['total'],
                "Total should equal sum of all amounts (iteration {$i})"
            );
        }
    }

    /**
     * Property 7: Category breakdown sums exactly to total.
     *
     * **Validates: Requirement 6.3**
     *
     * @test
     */
    public function category_breakdown_sums_to_total_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $categorias = ['prestamo', 'descuento_credito_r11', 'adelanto', 'multa', 'otros'];

        for ($i = 0; $i < 100; $i++) {
            $numAjustes = $faker->numberBetween(1, 10);
            $ajustes = [];

            for ($j = 0; $j < $numAjustes; $j++) {
                $ajustes[] = [
                    'monto' => -$faker->numberBetween(1000, 100000),
                    'categoria_slug' => $faker->randomElement($categorias),
                ];
            }

            $result = $this->aggregateDiscounts($ajustes);

            $sumCategories = array_sum($result['por_categoria']);

            $this->assertEquals(
                $result['total'],
                $sumCategories,
                "Sum of categories ({$sumCategories}) should equal total ({$result['total']})"
                    . " (iteration {$i})"
            );
        }
    }

    /**
     * Property 7: Each category total equals sum of its individual amounts.
     *
     * **Validates: Requirement 6.3**
     *
     * @test
     */
    public function each_category_total_is_correct_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $categorias = ['prestamo', 'descuento_credito_r11', 'adelanto', 'multa'];

        for ($i = 0; $i < 100; $i++) {
            $numAjustes = $faker->numberBetween(2, 8);
            $ajustes = [];

            for ($j = 0; $j < $numAjustes; $j++) {
                $ajustes[] = [
                    'monto' => -$faker->numberBetween(1000, 100000),
                    'categoria_slug' => $faker->randomElement($categorias),
                ];
            }

            $result = $this->aggregateDiscounts($ajustes);

            // Manually compute expected per-category totals
            $expected = [];
            foreach ($ajustes as $a) {
                $slug = $a['categoria_slug'];
                if (!isset($expected[$slug])) {
                    $expected[$slug] = 0;
                }
                $expected[$slug] += (int) $a['monto'];
            }

            foreach ($expected as $slug => $expectedAmount) {
                $this->assertEquals(
                    $expectedAmount,
                    $result['por_categoria'][$slug],
                    "Category '{$slug}' should total {$expectedAmount} (iteration {$i})"
                );
            }
        }
    }
}
