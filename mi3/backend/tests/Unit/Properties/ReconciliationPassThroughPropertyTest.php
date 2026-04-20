<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use App\Services\Compra\AiPromptService;
use App\Services\Compra\GeminiService;
use Eris\Generator;
use Eris\TestTrait;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 10: reconciliación sin inconsistencias es pass-through
 *
 * Para cualquier entrada al Agente Reconciliación con lista de inconsistencias vacía,
 * los datos de salida deben ser idénticos a los datos de entrada, y la lista de preguntas
 * debe estar vacía.
 *
 * **Validates: Requirements 4.5**
 */
class ReconciliationPassThroughPropertyTest extends TestCase
{
    use TestTrait;

    private GeminiService $geminiService;

    protected function setUp(): void
    {
        parent::setUp();
        $promptService = Mockery::mock(AiPromptService::class);
        $this->geminiService = new GeminiService($promptService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─── Generators ───

    private function generateRandomItem(): array
    {
        $nombres = ['Tomate', 'Papa', 'Cebolla', 'Lechuga', 'Palta', 'Zanahoria', 'Limón', 'Manzana'];
        $unidades = ['kg', 'unidad', 'litro', 'caja', 'bolsa'];
        $cantidad = rand(1, 50);
        $precioUnitario = rand(100, 30000);

        return [
            'nombre' => $nombres[array_rand($nombres)],
            'cantidad' => $cantidad,
            'unidad' => $unidades[array_rand($unidades)],
            'precio_unitario' => $precioUnitario,
            'subtotal' => $cantidad * $precioUnitario,
        ];
    }

    private function generateRandomData(): array
    {
        $proveedores = ['Distribuidora Central', 'Frutas del Sur', 'Carnicería Don Pedro', 'Verdulería La Vega'];
        $metodos = ['efectivo', 'transferencia', 'tarjeta'];
        $tipos = ['insumo', 'producto', 'servicio'];

        $numItems = rand(1, 5);
        $items = [];
        $totalItems = 0;
        for ($i = 0; $i < $numItems; $i++) {
            $item = $this->generateRandomItem();
            $items[] = $item;
            $totalItems += $item['subtotal'];
        }

        $montoNeto = $totalItems;
        $iva = (int) round($montoNeto * 0.19);

        return [
            'proveedor' => $proveedores[array_rand($proveedores)],
            'rut_proveedor' => '76.' . rand(100, 999) . '.' . rand(100, 999) . '-' . rand(0, 9),
            'fecha' => '2025-0' . rand(1, 9) . '-' . str_pad((string) rand(1, 28), 2, '0', STR_PAD_LEFT),
            'metodo_pago' => $metodos[array_rand($metodos)],
            'tipo_compra' => $tipos[array_rand($tipos)],
            'items' => $items,
            'monto_neto' => $montoNeto,
            'iva' => $iva,
            'monto_total' => $montoNeto + $iva,
        ];
    }

    // ─── Property Tests ───

    /**
     * Property: When inconsistencias is empty, output data must be identical to input data.
     *
     * The reconciliar() method short-circuits when inconsistencias is empty,
     * returning datos_finales = input datos without calling Gemini.
     */
    public function testEmptyInconsistenciasReturnsIdenticalData(): void
    {
        $this->forAll(
            Generator\choose(1, 100)  // seed for random data generation
        )
        ->withMaxSize(100)
        ->then(function (int $_seed): void {
            $inputData = $this->generateRandomData();
            $emptyInconsistencias = [];
            $textoCrudo = 'Texto crudo de prueba para reconciliación';
            $contextoBd = ['suppliers' => ['Distribuidora Central', 'Frutas del Sur']];

            $result = $this->geminiService->reconciliar(
                $inputData,
                $emptyInconsistencias,
                $textoCrudo,
                $contextoBd
            );

            $this->assertNotNull($result, 'reconciliar() must not return null for empty inconsistencias');
            $this->assertSame(
                $inputData,
                $result['datos_finales'],
                'Output data must be identical to input data when no inconsistencias'
            );
        });
    }

    /**
     * Property: When inconsistencias is empty, questions list must be empty.
     */
    public function testEmptyInconsistenciasReturnsEmptyQuestions(): void
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->withMaxSize(100)
        ->then(function (int $_seed): void {
            $inputData = $this->generateRandomData();
            $textoCrudo = 'Boleta de compra con productos varios';
            $contextoBd = ['suppliers' => ['Verdulería La Vega']];

            $result = $this->geminiService->reconciliar(
                $inputData,
                [],
                $textoCrudo,
                $contextoBd
            );

            $this->assertNotNull($result);
            $this->assertIsArray($result['preguntas']);
            $this->assertEmpty(
                $result['preguntas'],
                'Questions list must be empty when no inconsistencias'
            );
        });
    }

    /**
     * Property: When inconsistencias is empty, correcciones_aplicadas must be empty.
     */
    public function testEmptyInconsistenciasReturnsEmptyCorrections(): void
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->withMaxSize(100)
        ->then(function (int $_seed): void {
            $inputData = $this->generateRandomData();
            $textoCrudo = 'Factura electrónica proveedor';
            $contextoBd = ['suppliers' => ['Carnicería Don Pedro']];

            $result = $this->geminiService->reconciliar(
                $inputData,
                [],
                $textoCrudo,
                $contextoBd
            );

            $this->assertNotNull($result);
            $this->assertIsArray($result['correcciones_aplicadas']);
            $this->assertEmpty(
                $result['correcciones_aplicadas'],
                'Correcciones list must be empty when no inconsistencias'
            );
        });
    }

    /**
     * Property: When inconsistencias is empty, tokens must be zero (no API call made).
     */
    public function testEmptyInconsistenciasReturnsZeroTokens(): void
    {
        $this->forAll(
            Generator\choose(1, 100)
        )
        ->withMaxSize(100)
        ->then(function (int $_seed): void {
            $inputData = $this->generateRandomData();
            $textoCrudo = 'Texto de prueba';
            $contextoBd = ['suppliers' => []];

            $result = $this->geminiService->reconciliar(
                $inputData,
                [],
                $textoCrudo,
                $contextoBd
            );

            $this->assertNotNull($result);
            $this->assertSame(0, $result['tokens']['prompt']);
            $this->assertSame(0, $result['tokens']['candidates']);
            $this->assertSame(0, $result['tokens']['total']);
        });
    }

    /**
     * Property: Pass-through preserves all data fields regardless of data complexity.
     */
    public function testPassThroughPreservesAllFieldsRegardlessOfComplexity(): void
    {
        $this->forAll(
            Generator\choose(1, 8),   // number of items
            Generator\choose(1, 100)  // seed
        )
        ->withMaxSize(100)
        ->then(function (int $numItems, int $_seed): void {
            $items = [];
            for ($i = 0; $i < $numItems; $i++) {
                $items[] = $this->generateRandomItem();
            }

            $inputData = $this->generateRandomData();
            $inputData['items'] = $items;

            $result = $this->geminiService->reconciliar(
                $inputData,
                [],
                'Texto crudo',
                ['suppliers' => []]
            );

            $this->assertNotNull($result);

            // Verify every field is preserved
            $this->assertSame($inputData['proveedor'], $result['datos_finales']['proveedor']);
            $this->assertSame($inputData['monto_total'], $result['datos_finales']['monto_total']);
            $this->assertSame($inputData['monto_neto'], $result['datos_finales']['monto_neto']);
            $this->assertSame($inputData['iva'], $result['datos_finales']['iva']);
            $this->assertCount($numItems, $result['datos_finales']['items']);

            // Verify each item is preserved
            for ($i = 0; $i < $numItems; $i++) {
                $this->assertSame(
                    $inputData['items'][$i]['nombre'],
                    $result['datos_finales']['items'][$i]['nombre']
                );
                $this->assertSame(
                    $inputData['items'][$i]['subtotal'],
                    $result['datos_finales']['items'][$i]['subtotal']
                );
            }
        });
    }
}
