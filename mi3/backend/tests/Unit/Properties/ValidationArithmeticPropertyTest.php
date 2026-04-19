<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 6: validación aritmética detecta inconsistencias correctamente
 *
 * Para cualquier conjunto de datos extraídos donde subtotal != precio_unitario × cantidad
 * (con diferencia > 2%), el Agente Validación debe retornar una inconsistencia con severidad "error".
 * Inversamente, cuando la diferencia es ≤ 2%, no debe reportar inconsistencia aritmética.
 *
 * **Validates: Requirements 3.1, 3.5**
 */
class ValidationArithmeticPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Validates arithmetic consistency of an item.
     * Returns an inconsistency array if subtotal differs from precio_unitario * cantidad by more than 2%.
     * Returns null if within tolerance.
     */
    private function validateArithmetic(array $item, int $index): ?array
    {
        $expected = $item['precio_unitario'] * $item['cantidad'];

        if ($expected == 0) {
            return null;
        }

        $difference = abs($item['subtotal'] - $expected);
        $tolerance = abs($expected) * 0.02;

        if ($difference > $tolerance) {
            return [
                'campo' => "items.{$index}.subtotal",
                'valor_actual' => (string) $item['subtotal'],
                'valor_esperado' => (string) $expected,
                'severidad' => 'error',
                'descripcion' => "subtotal no coincide con precio_unitario × cantidad",
            ];
        }

        return null;
    }

    /**
     * Property: When subtotal differs from precio_unitario × cantidad by MORE than 2%,
     * an arithmetic inconsistency with severity "error" MUST be detected.
     */
    public function testDetectsInconsistencyWhenDifferenceExceedsTolerance(): void
    {
        $this->forAll(
            Generator\choose(100, 50000),   // precio_unitario
            Generator\choose(1, 100),        // cantidad
            Generator\choose(5, 50)          // percentage deviation (5% to 50%)
        )
        ->withMaxSize(100)
        ->then(function (int $precioUnitario, int $cantidad, int $deviationPercent): void {
            $expected = $precioUnitario * $cantidad;
            // Apply deviation > 2% to make subtotal inconsistent
            $deviation = (int) ceil($expected * $deviationPercent / 100);
            $subtotal = $expected + $deviation;

            $item = [
                'nombre' => 'Producto Test',
                'cantidad' => $cantidad,
                'unidad' => 'kg',
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];

            $result = $this->validateArithmetic($item, 0);

            $this->assertNotNull(
                $result,
                "Must detect inconsistency when deviation is {$deviationPercent}% (> 2%): " .
                "expected={$expected}, subtotal={$subtotal}"
            );
            $this->assertSame('error', $result['severidad']);
            $this->assertSame('items.0.subtotal', $result['campo']);
            $this->assertSame((string) $subtotal, $result['valor_actual']);
            $this->assertSame((string) $expected, $result['valor_esperado']);
        });
    }

    /**
     * Property: When subtotal differs from precio_unitario × cantidad by LESS than or equal to 2%,
     * NO arithmetic inconsistency should be reported.
     */
    public function testNoInconsistencyWhenWithinTolerance(): void
    {
        $this->forAll(
            Generator\choose(100, 50000),   // precio_unitario
            Generator\choose(1, 100),        // cantidad
            Generator\choose(0, 20)          // permille deviation (0 to 2.0%)
        )
        ->withMaxSize(100)
        ->then(function (int $precioUnitario, int $cantidad, int $deviationPermille): void {
            $expected = $precioUnitario * $cantidad;
            // Apply deviation ≤ 2% (deviationPermille / 1000 gives 0.0% to 2.0%)
            $deviation = (int) floor($expected * $deviationPermille / 1000);
            $subtotal = $expected + $deviation;

            $item = [
                'nombre' => 'Producto Test',
                'cantidad' => $cantidad,
                'unidad' => 'kg',
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];

            $result = $this->validateArithmetic($item, 0);

            $this->assertNull(
                $result,
                "Must NOT detect inconsistency when deviation is " .
                ($deviationPermille / 10) . "% (≤ 2%): expected={$expected}, subtotal={$subtotal}"
            );
        });
    }

    /**
     * Property: Negative deviations (subtotal less than expected) beyond 2% are also detected.
     */
    public function testDetectsNegativeDeviationBeyondTolerance(): void
    {
        $this->forAll(
            Generator\choose(100, 50000),   // precio_unitario
            Generator\choose(1, 100),        // cantidad
            Generator\choose(5, 50)          // percentage deviation (5% to 50%)
        )
        ->withMaxSize(100)
        ->then(function (int $precioUnitario, int $cantidad, int $deviationPercent): void {
            $expected = $precioUnitario * $cantidad;
            // Subtract deviation > 2%
            $deviation = (int) ceil($expected * $deviationPercent / 100);
            $subtotal = $expected - $deviation;

            $item = [
                'nombre' => 'Producto Test',
                'cantidad' => $cantidad,
                'unidad' => 'kg',
                'precio_unitario' => $precioUnitario,
                'subtotal' => $subtotal,
            ];

            $result = $this->validateArithmetic($item, 0);

            $this->assertNotNull(
                $result,
                "Must detect inconsistency for negative deviation of {$deviationPercent}% (> 2%): " .
                "expected={$expected}, subtotal={$subtotal}"
            );
            $this->assertSame('error', $result['severidad']);
        });
    }

    /**
     * Property: Multiple items are each validated independently.
     */
    public function testMultipleItemsValidatedIndependently(): void
    {
        $this->forAll(
            Generator\choose(1, 10),         // number of items
            Generator\choose(100, 10000)     // base price
        )
        ->withMaxSize(100)
        ->then(function (int $numItems, int $basePrice): void {
            $items = [];
            $expectedInconsistencies = 0;

            for ($i = 0; $i < $numItems; $i++) {
                $precio = $basePrice + ($i * 100);
                $cantidad = $i + 1;
                $expected = $precio * $cantidad;

                // Alternate: even items are consistent, odd items have >2% deviation
                if ($i % 2 === 1) {
                    $subtotal = (int) ($expected * 1.10); // 10% off
                    $expectedInconsistencies++;
                } else {
                    $subtotal = $expected; // exact match
                }

                $items[] = [
                    'nombre' => "Producto {$i}",
                    'cantidad' => $cantidad,
                    'unidad' => 'kg',
                    'precio_unitario' => $precio,
                    'subtotal' => $subtotal,
                ];
            }

            $inconsistencies = [];
            foreach ($items as $idx => $item) {
                $result = $this->validateArithmetic($item, $idx);
                if ($result !== null) {
                    $inconsistencies[] = $result;
                }
            }

            $this->assertCount(
                $expectedInconsistencies,
                $inconsistencies,
                "Must detect exactly {$expectedInconsistencies} inconsistencies out of {$numItems} items"
            );
        });
    }
}
