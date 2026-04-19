<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use App\Services\Compra\FeedbackService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 5: inyección correcta de few-shot examples
 *
 * Para cualquier proveedor/tipo con N registros en extraction_feedback:
 * si N > 0, el prompt del Agente Análisis debe contener min(N, 5) ejemplos formateados
 * como correcciones; si N = 0, el prompt no debe contener sección de correcciones.
 *
 * **Validates: Requirements 2.4, 6.3, 6.5**
 */
class FewShotInjectionPropertyTest extends TestCase
{
    use TestTrait;

    private FeedbackService $feedbackService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->feedbackService = new FeedbackService();
    }

    /**
     * Generator for a random proveedor name.
     */
    private function proveedorGenerator(): Generator
    {
        return Generator\elements([
            'Distribuidora Central',
            'Frutas del Sur',
            'Carnicería Don Pedro',
            'Verdulería La Vega',
            'Panadería El Trigo',
            'Supermercado Líder',
            'Feria Lo Valledor',
            'Abarrotes San José',
        ]);
    }

    /**
     * Generator for a random field_name.
     */
    private function fieldNameGenerator(): Generator
    {
        return Generator\elements([
            'proveedor',
            'monto_total',
            'fecha',
            'rut_proveedor',
            'metodo_pago',
            'items.0.nombre',
            'items.0.cantidad',
            'items.0.precio_unitario',
            'items.1.nombre',
            'items.1.subtotal',
            'monto_neto',
            'iva',
        ]);
    }

    /**
     * Generator for a random original/corrected value.
     */
    private function valueGenerator(): Generator
    {
        return Generator\elements([
            'Tomate',
            'Papa',
            '15000',
            '2500',
            '12-01-2025',
            'efectivo',
            '76.123.456-7',
            'Palta Hass',
            '3.5',
            'kg',
            'unidad',
            '1190',
            'Lechuga',
        ]);
    }

    /**
     * Generator for a single feedback correction record.
     */
    private function correctionGenerator(): Generator
    {
        return Generator\map(
            function (array $tuple): array {
                return [
                    'proveedor' => $tuple[0],
                    'field_name' => $tuple[1],
                    'original_value' => $tuple[2],
                    'corrected_value' => $tuple[3],
                ];
            },
            Generator\tuple(
                $this->proveedorGenerator(),
                $this->fieldNameGenerator(),
                $this->valueGenerator(),
                $this->valueGenerator()
            )
        );
    }

    /**
     * Property: formatearEjemplos() with N corrections produces exactly N lines.
     *
     * For any array of N corrections (1 to 10), formatearEjemplos() must return
     * a string with exactly N lines (separated by newlines).
     */
    public function testFormatearEjemplosProducesExactlyNLines(): void
    {
        $this->forAll(
            Generator\choose(1, 10)
        )
        ->withMaxSize(100)
        ->then(function (int $n): void {
            $corrections = $this->generateCorrections($n);
            $result = $this->feedbackService->formatearEjemplos($corrections);

            $lines = explode("\n", $result);
            $this->assertCount(
                $n,
                $lines,
                "formatearEjemplos() with {$n} corrections must produce exactly {$n} lines"
            );
        });
    }

    /**
     * Property: formatearEjemplos() with empty array returns empty string.
     *
     * For any call with an empty corrections array, the result must be an empty string
     * (no corrections section in prompt).
     */
    public function testFormatearEjemplosWithEmptyArrayReturnsEmptyString(): void
    {
        $this->forAll(
            Generator\constant(0)
        )
        ->withMaxSize(100)
        ->then(function (int $_): void {
            $result = $this->feedbackService->formatearEjemplos([]);

            $this->assertSame(
                '',
                $result,
                'formatearEjemplos() with empty array must return empty string (no corrections section)'
            );
        });
    }

    /**
     * Property: Each formatted line contains proveedor, field_name, original_value, corrected_value.
     *
     * For any correction record, the formatted output line must contain all four key values.
     */
    public function testEachFormattedLineContainsAllCorrectionFields(): void
    {
        $this->forAll(
            $this->proveedorGenerator(),
            $this->fieldNameGenerator(),
            $this->valueGenerator(),
            $this->valueGenerator()
        )
        ->withMaxSize(100)
        ->then(function (string $proveedor, string $field, string $original, string $corrected): void {
            $correction = [
                'proveedor' => $proveedor,
                'field_name' => $field,
                'original_value' => $original,
                'corrected_value' => $corrected,
            ];

            $result = $this->feedbackService->formatearEjemplos([$correction]);

            $this->assertStringContainsString(
                $proveedor,
                $result,
                'Formatted line must contain the proveedor'
            );
            $this->assertStringContainsString(
                $field,
                $result,
                'Formatted line must contain the field_name'
            );
            $this->assertStringContainsString(
                $original,
                $result,
                'Formatted line must contain the original_value'
            );
            $this->assertStringContainsString(
                $corrected,
                $result,
                'Formatted line must contain the corrected_value'
            );
        });
    }

    /**
     * Property: getFewShotExamples returns at most $limit results.
     *
     * For any N corrections and any limit, formatearEjemplos applied to
     * min(N, limit) corrections produces at most limit lines.
     */
    public function testFewShotExamplesRespectLimit(): void
    {
        $this->forAll(
            Generator\choose(1, 15),
            Generator\choose(1, 5)
        )
        ->withMaxSize(100)
        ->then(function (int $totalCorrections, int $limit): void {
            $corrections = $this->generateCorrections($totalCorrections);

            // Simulate the limit behavior (as getFewShotExamples applies LIMIT in SQL)
            $limited = array_slice($corrections, 0, $limit);
            $result = $this->feedbackService->formatearEjemplos($limited);

            $lines = explode("\n", $result);
            $expectedCount = min($totalCorrections, $limit);

            $this->assertCount(
                $expectedCount,
                $lines,
                "Should have exactly min(N={$totalCorrections}, limit={$limit}) = {$expectedCount} lines"
            );
            $this->assertLessThanOrEqual(
                $limit,
                count($lines),
                "Output must never exceed the limit of {$limit} lines"
            );
        });
    }

    /**
     * Property: The format matches the expected pattern exactly.
     *
     * For any correction, the formatted line must match:
     * "En extracciones anteriores de [proveedor], el usuario corrigió [campo] de '[original]' a '[corregido]'"
     */
    public function testFormatMatchesExpectedPattern(): void
    {
        $this->forAll(
            $this->proveedorGenerator(),
            $this->fieldNameGenerator(),
            $this->valueGenerator(),
            $this->valueGenerator()
        )
        ->withMaxSize(100)
        ->then(function (string $proveedor, string $field, string $original, string $corrected): void {
            $correction = [
                'proveedor' => $proveedor,
                'field_name' => $field,
                'original_value' => $original,
                'corrected_value' => $corrected,
            ];

            $result = $this->feedbackService->formatearEjemplos([$correction]);

            $expected = "En extracciones anteriores de {$proveedor}, el usuario corrigió {$field} de '{$original}' a '{$corrected}'";

            $this->assertSame(
                $expected,
                $result,
                'Formatted line must match the exact expected pattern'
            );
        });
    }

    /**
     * Helper: Generate N random correction records.
     */
    private function generateCorrections(int $n): array
    {
        $proveedores = [
            'Distribuidora Central', 'Frutas del Sur', 'Carnicería Don Pedro',
            'Verdulería La Vega', 'Panadería El Trigo', 'Supermercado Líder',
        ];
        $fields = [
            'proveedor', 'monto_total', 'fecha', 'rut_proveedor',
            'items.0.nombre', 'items.0.cantidad', 'items.1.subtotal',
        ];
        $values = ['Tomate', 'Papa', '15000', '2500', 'efectivo', 'kg', 'Lechuga', '1190'];

        $corrections = [];
        for ($i = 0; $i < $n; $i++) {
            $corrections[] = [
                'proveedor' => $proveedores[array_rand($proveedores)],
                'field_name' => $fields[array_rand($fields)],
                'original_value' => $values[array_rand($values)],
                'corrected_value' => $values[array_rand($values)],
            ];
        }

        return $corrections;
    }
}
