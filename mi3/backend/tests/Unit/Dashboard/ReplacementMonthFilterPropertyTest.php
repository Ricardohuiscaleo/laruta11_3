<?php

namespace Tests\Unit\Dashboard;

use Carbon\Carbon;
use Faker\Factory as Faker;
use Tests\TestCase;

/**
 * Feature: mi3-worker-dashboard-v2, Property 9: Filtrado de reemplazos por mes
 *
 * Validates: Requirement 8.4
 *
 * Property: For any queried month, all replacement shifts returned must have a date
 * within that month. No shift from another month should appear in the results.
 */
class ReplacementMonthFilterPropertyTest extends TestCase
{
    /**
     * Simulate the month filtering logic from ReplacementController.
     *
     * @param array  $turnos Array of ['fecha' => 'Y-m-d', 'tipo' => string, ...]
     * @param string $mes    Month in YYYY-MM format
     * @return array Filtered turnos
     */
    private function filterByMonth(array $turnos, string $mes): array
    {
        $inicioMes = Carbon::parse($mes . '-01')->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->endOfDay();
        $tiposReemplazo = ['reemplazo', 'reemplazo_seguridad'];

        return array_values(array_filter($turnos, function ($turno) use ($inicioMes, $finMes, $tiposReemplazo) {
            $fecha = Carbon::parse($turno['fecha']);
            return in_array($turno['tipo'], $tiposReemplazo)
                && $fecha->between($inicioMes, $finMes);
        }));
    }

    /**
     * Property 9: All returned shifts have dates within the queried month.
     *
     * **Validates: Requirement 8.4**
     *
     * @test
     */
    public function all_returned_shifts_are_within_queried_month_for_100_random_inputs(): void
    {
        $faker = Faker::create();
        $tipos = ['reemplazo', 'reemplazo_seguridad', 'normal', 'seguridad'];

        for ($i = 0; $i < 100; $i++) {
            // Pick a random target month
            $year = $faker->numberBetween(2024, 2026);
            $month = $faker->numberBetween(1, 12);
            $mes = sprintf('%04d-%02d', $year, $month);

            // Generate shifts across multiple months
            $numTurnos = $faker->numberBetween(5, 20);
            $turnos = [];

            for ($j = 0; $j < $numTurnos; $j++) {
                // Random date within ±2 months of target
                $offsetMonths = $faker->numberBetween(-2, 2);
                $fecha = Carbon::parse($mes . '-01')
                    ->addMonths($offsetMonths)
                    ->addDays($faker->numberBetween(0, 27))
                    ->format('Y-m-d');

                $turnos[] = [
                    'fecha' => $fecha,
                    'tipo' => $faker->randomElement($tipos),
                ];
            }

            $filtered = $this->filterByMonth($turnos, $mes);

            // Assert all returned shifts are within the target month
            foreach ($filtered as $turno) {
                $turnoDate = Carbon::parse($turno['fecha']);
                $this->assertEquals(
                    $year,
                    (int) $turnoDate->format('Y'),
                    "Shift date {$turno['fecha']} year should be {$year} (iteration {$i})"
                );
                $this->assertEquals(
                    $month,
                    (int) $turnoDate->format('m'),
                    "Shift date {$turno['fecha']} month should be {$month} (iteration {$i})"
                );
            }
        }
    }

    /**
     * Property 9: No shift from another month appears in results.
     *
     * **Validates: Requirement 8.4**
     *
     * @test
     */
    public function no_shifts_from_other_months_appear_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $year = $faker->numberBetween(2024, 2026);
            $month = $faker->numberBetween(1, 12);
            $mes = sprintf('%04d-%02d', $year, $month);

            // Generate shifts ONLY outside the target month
            $numTurnos = $faker->numberBetween(3, 10);
            $turnos = [];

            for ($j = 0; $j < $numTurnos; $j++) {
                // Offset by ±1 or ±2 months (never 0)
                $offsetMonths = $faker->randomElement([-2, -1, 1, 2]);
                $fecha = Carbon::parse($mes . '-01')
                    ->addMonths($offsetMonths)
                    ->addDays($faker->numberBetween(0, 27))
                    ->format('Y-m-d');

                $turnos[] = [
                    'fecha' => $fecha,
                    'tipo' => $faker->randomElement(['reemplazo', 'reemplazo_seguridad']),
                ];
            }

            $filtered = $this->filterByMonth($turnos, $mes);

            $this->assertEmpty(
                $filtered,
                "No shifts from other months should appear for month {$mes} (iteration {$i})"
            );
        }
    }

    /**
     * Property 9: Only replacement types are included (normal/seguridad excluded).
     *
     * **Validates: Requirement 8.4**
     *
     * @test
     */
    public function only_replacement_types_are_included_for_100_random_inputs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < 100; $i++) {
            $year = $faker->numberBetween(2024, 2026);
            $month = $faker->numberBetween(1, 12);
            $mes = sprintf('%04d-%02d', $year, $month);

            // Generate shifts within the target month but with non-replacement types
            $numTurnos = $faker->numberBetween(3, 10);
            $turnos = [];

            for ($j = 0; $j < $numTurnos; $j++) {
                $day = $faker->numberBetween(1, 28);
                $fecha = sprintf('%s-%02d', $mes, $day);

                $turnos[] = [
                    'fecha' => $fecha,
                    'tipo' => $faker->randomElement(['normal', 'seguridad']),
                ];
            }

            $filtered = $this->filterByMonth($turnos, $mes);

            $this->assertEmpty(
                $filtered,
                "Non-replacement types should be excluded for month {$mes} (iteration {$i})"
            );
        }
    }
}
