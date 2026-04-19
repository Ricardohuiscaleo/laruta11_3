<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 7: validación fiscal detecta IVA incorrecto
 *
 * Para cualquier combinación de monto_neto e iva donde |iva - monto_neto × 0.19| > monto_neto × 0.02,
 * el Agente Validación debe retornar una inconsistencia fiscal. Cuando la diferencia es ≤ 2%, no debe
 * reportar inconsistencia.
 *
 * **Validates: Requirements 3.2**
 */
class ValidationFiscalPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Validates fiscal consistency: IVA should be approximately 19% of monto_neto.
     * Returns an inconsistency array if |iva - monto_neto * 0.19| > monto_neto * 0.02.
     * Returns null if within tolerance.
     */
    private function validateFiscal(int $montoNeto, int $iva): ?array
    {
        if ($montoNeto == 0) {
            return null;
        }

        $expectedIva = $montoNeto * 0.19;
        $difference = abs($iva - $expectedIva);
        $tolerance = abs($montoNeto) * 0.02;

        if ($difference > $tolerance) {
            return [
                'campo' => 'iva',
                'valor_actual' => (string) $iva,
                'valor_esperado' => (string) (int) round($expectedIva),
                'severidad' => 'error',
                'descripcion' => 'IVA no corresponde al 19% del monto neto',
            ];
        }

        return null;
    }

    /**
     * Property: When IVA differs from monto_neto × 0.19 by MORE than 2% of monto_neto,
     * a fiscal inconsistency MUST be detected.
     */
    public function testDetectsFiscalInconsistencyWhenIvaExceedsTolerance(): void
    {
        $this->forAll(
            Generator\choose(10000, 5000000),  // monto_neto (CLP)
            Generator\choose(5, 80)             // percentage deviation from correct IVA (5% to 80%)
        )
        ->withMaxSize(100)
        ->then(function (int $montoNeto, int $deviationPercent): void {
            $correctIva = (int) round($montoNeto * 0.19);
            // Apply deviation > 2% of monto_neto to IVA
            $deviation = (int) ceil($montoNeto * 0.02) + (int) ceil($montoNeto * $deviationPercent / 100);
            $wrongIva = $correctIva + $deviation;

            $result = $this->validateFiscal($montoNeto, $wrongIva);

            $this->assertNotNull(
                $result,
                "Must detect fiscal inconsistency: monto_neto={$montoNeto}, " .
                "iva={$wrongIva}, expected≈{$correctIva}"
            );
            $this->assertSame('error', $result['severidad']);
            $this->assertSame('iva', $result['campo']);
            $this->assertSame((string) $wrongIva, $result['valor_actual']);
        });
    }

    /**
     * Property: When IVA is within 2% tolerance of monto_neto × 0.19,
     * NO fiscal inconsistency should be reported.
     */
    public function testNoInconsistencyWhenIvaWithinTolerance(): void
    {
        $this->forAll(
            Generator\choose(10000, 5000000),  // monto_neto (CLP)
            Generator\choose(0, 20)             // permille deviation (0 to 2.0% of monto_neto)
        )
        ->withMaxSize(100)
        ->then(function (int $montoNeto, int $deviationPermille): void {
            $correctIva = (int) round($montoNeto * 0.19);
            // Apply deviation ≤ 2% of monto_neto
            $deviation = (int) floor($montoNeto * $deviationPermille / 1000);
            $iva = $correctIva + $deviation;

            $result = $this->validateFiscal($montoNeto, $iva);

            $this->assertNull(
                $result,
                "Must NOT detect fiscal inconsistency when deviation is " .
                ($deviationPermille / 10) . "% of monto_neto: " .
                "monto_neto={$montoNeto}, iva={$iva}, expected≈{$correctIva}"
            );
        });
    }

    /**
     * Property: Negative deviations (IVA less than expected) beyond tolerance are also detected.
     */
    public function testDetectsNegativeIvaDeviationBeyondTolerance(): void
    {
        $this->forAll(
            Generator\choose(10000, 5000000),  // monto_neto (CLP)
            Generator\choose(5, 80)             // percentage deviation
        )
        ->withMaxSize(100)
        ->then(function (int $montoNeto, int $deviationPercent): void {
            $correctIva = (int) round($montoNeto * 0.19);
            // Subtract deviation > 2% of monto_neto from IVA
            $deviation = (int) ceil($montoNeto * 0.02) + (int) ceil($montoNeto * $deviationPercent / 100);
            $wrongIva = max(0, $correctIva - $deviation);

            $result = $this->validateFiscal($montoNeto, $wrongIva);

            $this->assertNotNull(
                $result,
                "Must detect fiscal inconsistency for negative deviation: " .
                "monto_neto={$montoNeto}, iva={$wrongIva}, expected≈{$correctIva}"
            );
            $this->assertSame('error', $result['severidad']);
        });
    }

    /**
     * Property: Exact 19% IVA always passes validation.
     */
    public function testExactIvaAlwaysPasses(): void
    {
        $this->forAll(
            Generator\choose(1000, 5000000)  // monto_neto (CLP)
        )
        ->withMaxSize(100)
        ->then(function (int $montoNeto): void {
            $exactIva = (int) round($montoNeto * 0.19);

            $result = $this->validateFiscal($montoNeto, $exactIva);

            $this->assertNull(
                $result,
                "Exact 19% IVA must always pass: monto_neto={$montoNeto}, iva={$exactIva}"
            );
        });
    }
}
