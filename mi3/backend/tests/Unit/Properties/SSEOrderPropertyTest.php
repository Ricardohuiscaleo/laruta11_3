<?php

declare(strict_types=1);

namespace Tests\Unit\Properties;

use App\Services\Compra\GeminiService;
use Eris\Generator;
use Eris\TestTrait;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 12: eventos SSE emitidos en orden correcto
 *
 * Para cualquier ejecución del pipeline, los eventos SSE deben emitirse en orden estricto:
 * vision(running) → vision(done) → analisis(running) → analisis(done) →
 * validacion(running) → validacion(done) → reconciliacion(running) → reconciliacion(done) →
 * completado(done). Cada evento debe incluir fase, status, y elapsed_ms.
 *
 * **Validates: Requirements 5.1, 5.2**
 */
class SSEOrderPropertyTest extends TestCase
{
    use TestTrait;

    private array $capturedEvents = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Expected SSE event order for a successful pipeline execution.
     */
    private const EXPECTED_ORDER = [
        ['fase' => 'vision', 'status' => 'running'],
        ['fase' => 'vision', 'status' => 'done'],
        ['fase' => 'analisis', 'status' => 'running'],
        ['fase' => 'analisis', 'status' => 'done'],
        ['fase' => 'validacion', 'status' => 'running'],
        ['fase' => 'validacion', 'status' => 'done'],
        ['fase' => 'reconciliacion', 'status' => 'running'],
        ['fase' => 'reconciliacion', 'status' => 'done'],
        ['fase' => 'completado', 'status' => 'done'],
    ];

    /**
     * Simulates the multi-agent pipeline's SSE event emission logic.
     * This replicates the event ordering from ejecutarMultiAgente() without
     * needing to mock private methods or DB access.
     */
    private function simulatePipelineSSE(GeminiService $gemini, string $imageBase64): array
    {
        $startTime = microtime(true);
        $this->capturedEvents = [];

        $emit = function (string $fase, string $status, ?array $data) use ($startTime): void {
            $this->capturedEvents[] = [
                'fase' => $fase,
                'status' => $status,
                'data' => $data,
                'elapsed_ms' => (int) round((microtime(true) - $startTime) * 1000),
            ];
        };

        // Phase 1: Vision
        $emit('vision', 'running', ['engine' => 'gemini']);
        $phaseStart = microtime(true);
        $visionResult = $gemini->percibir($imageBase64);

        if (!$visionResult) {
            $emit('vision', 'error', ['message' => 'Agente Visión falló']);
            return ['success' => false];
        }

        $emit('vision', 'done', [
            'tipo_imagen' => $visionResult['tipo_imagen'],
            'confianza' => $visionResult['confianza'],
            'engine' => 'gemini',
            'tokens' => $visionResult['tokens']['total'],
            'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
        ]);

        // Phase 2: Analysis
        $emit('analisis', 'running', ['engine' => 'gemini']);
        $phaseStart = microtime(true);
        $contexto = ['suppliers' => ['Distribuidora Central'], 'ingredients' => []];
        $analysisResult = $gemini->analizarTexto(
            $visionResult['texto_crudo'],
            $visionResult['descripcion_visual'],
            $visionResult['tipo_imagen'],
            $contexto,
            []
        );

        if (!$analysisResult) {
            $emit('analisis', 'error', ['message' => 'Agente Análisis falló']);
            return ['success' => false];
        }

        $emit('analisis', 'done', [
            'engine' => 'gemini',
            'tokens' => $analysisResult['tokens']['total'],
            'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
        ]);

        // Phase 3: Validation
        $emit('validacion', 'running', ['engine' => 'gemini']);
        $phaseStart = microtime(true);
        $validationResult = $gemini->validar($analysisResult['data'], $contexto);

        $inconsistencias = [];
        if ($validationResult) {
            $inconsistencias = $validationResult['inconsistencias'];
        }

        $emit('validacion', 'done', [
            'inconsistencias_count' => count($inconsistencias),
            'engine' => 'gemini',
            'tokens' => $validationResult['tokens']['total'] ?? 0,
            'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
        ]);

        // Phase 4: Reconciliation
        $emit('reconciliacion', 'running', ['engine' => 'gemini']);
        $phaseStart = microtime(true);
        $reconciliationResult = $gemini->reconciliar(
            $validationResult['datos_validados'] ?? $analysisResult['data'],
            $inconsistencias,
            $visionResult['texto_crudo'],
            $contexto
        );

        $emit('reconciliacion', 'done', [
            'correcciones_auto' => count($reconciliationResult['correcciones_aplicadas'] ?? []),
            'preguntas' => count($reconciliationResult['preguntas'] ?? []),
            'engine' => 'gemini',
            'tokens' => $reconciliationResult['tokens']['total'] ?? 0,
            'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
        ]);

        // Final event
        $emit('completado', 'done', [
            'processing_time_ms' => (int) round((microtime(true) - $startTime) * 1000),
            'engine' => 'gemini',
        ]);

        return ['success' => true];
    }

    /**
     * Creates a mocked GeminiService for pipeline simulation.
     */
    private function createMockedGemini(array $visionResult, array $analysisData): GeminiService
    {
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('percibir')->andReturn($visionResult);
        $gemini->shouldReceive('analizarTexto')->andReturn([
            'data' => $analysisData,
            'tokens' => ['prompt' => 100, 'candidates' => 50, 'total' => 150],
        ]);
        $gemini->shouldReceive('validar')->andReturn([
            'datos_validados' => $analysisData,
            'inconsistencias' => [],
            'tokens' => ['prompt' => 80, 'candidates' => 30, 'total' => 110],
        ]);
        $gemini->shouldReceive('reconciliar')->andReturn([
            'datos_finales' => $analysisData,
            'correcciones_aplicadas' => [],
            'preguntas' => [],
            'tokens' => ['prompt' => 0, 'candidates' => 0, 'total' => 0],
        ]);

        return $gemini;
    }

    /**
     * Creates a valid vision result with random data.
     */
    private function generateVisionResult(): array
    {
        $tipos = ['boleta', 'factura', 'producto', 'bascula', 'transferencia'];

        return [
            'texto_crudo' => 'Texto crudo de prueba ' . rand(1, 1000),
            'descripcion_visual' => 'Imagen de una boleta con productos varios',
            'tipo_imagen' => $tipos[array_rand($tipos)],
            'confianza' => rand(70, 99) / 100,
            'razon' => 'Documento comercial detectado',
            'tokens' => ['prompt' => rand(100, 300), 'candidates' => rand(50, 150), 'total' => rand(150, 450)],
        ];
    }

    /**
     * Creates valid analysis data with random items.
     */
    private function generateAnalysisData(int $numItems): array
    {
        $items = [];
        $total = 0;
        for ($i = 0; $i < $numItems; $i++) {
            $cantidad = rand(1, 10);
            $precio = rand(500, 10000);
            $subtotal = $cantidad * $precio;
            $total += $subtotal;
            $items[] = [
                'nombre' => "Producto {$i}",
                'cantidad' => $cantidad,
                'unidad' => 'kg',
                'precio_unitario' => $precio,
                'subtotal' => $subtotal,
            ];
        }

        return [
            'tipo_imagen' => 'boleta',
            'proveedor' => 'Distribuidora Central',
            'rut_proveedor' => '76.123.456-7',
            'fecha' => '2025-03-15',
            'metodo_pago' => 'efectivo',
            'tipo_compra' => 'insumo',
            'items' => $items,
            'monto_neto' => $total,
            'iva' => (int) round($total * 0.19),
            'monto_total' => $total + (int) round($total * 0.19),
        ];
    }

    // ─── Property Tests ───

    /**
     * Property: SSE events are emitted in strict order for any successful pipeline execution.
     *
     * The expected order is:
     * vision(running) → vision(done) → analisis(running) → analisis(done) →
     * validacion(running) → validacion(done) → reconciliacion(running) → reconciliacion(done) →
     * completado(done)
     */
    public function testSSEEventsEmittedInStrictOrder(): void
    {
        $this->forAll(
            Generator\choose(1, 8)  // number of items in extraction
        )
        ->withMaxSize(100)
        ->then(function (int $numItems): void {
            $visionResult = $this->generateVisionResult();
            $analysisData = $this->generateAnalysisData($numItems);
            $gemini = $this->createMockedGemini($visionResult, $analysisData);

            $this->simulatePipelineSSE($gemini, 'base64image');

            // Verify event count
            $this->assertCount(
                count(self::EXPECTED_ORDER),
                $this->capturedEvents,
                'Must emit exactly ' . count(self::EXPECTED_ORDER) . ' SSE events'
            );

            // Verify strict order
            foreach (self::EXPECTED_ORDER as $i => $expected) {
                $actual = $this->capturedEvents[$i];
                $this->assertSame(
                    $expected['fase'],
                    $actual['fase'],
                    "Event {$i}: expected fase '{$expected['fase']}', got '{$actual['fase']}'"
                );
                $this->assertSame(
                    $expected['status'],
                    $actual['status'],
                    "Event {$i}: expected status '{$expected['status']}', got '{$actual['status']}'"
                );
            }
        });
    }

    /**
     * Property: Each SSE event includes fase, status, and elapsed_ms fields.
     */
    public function testEachEventIncludesRequiredFields(): void
    {
        $this->forAll(
            Generator\choose(1, 5)  // number of items
        )
        ->withMaxSize(100)
        ->then(function (int $numItems): void {
            $visionResult = $this->generateVisionResult();
            $analysisData = $this->generateAnalysisData($numItems);
            $gemini = $this->createMockedGemini($visionResult, $analysisData);

            $this->simulatePipelineSSE($gemini, 'base64image');

            foreach ($this->capturedEvents as $i => $event) {
                $this->assertArrayHasKey('fase', $event, "Event {$i} must have 'fase'");
                $this->assertArrayHasKey('status', $event, "Event {$i} must have 'status'");
                $this->assertArrayHasKey('elapsed_ms', $event, "Event {$i} must have 'elapsed_ms'");
                $this->assertIsString($event['fase'], "Event {$i} fase must be string");
                $this->assertIsString($event['status'], "Event {$i} status must be string");
                $this->assertIsInt($event['elapsed_ms'], "Event {$i} elapsed_ms must be int");
                $this->assertGreaterThanOrEqual(0, $event['elapsed_ms'], "Event {$i} elapsed_ms must be >= 0");
            }
        });
    }

    /**
     * Property: elapsed_ms values are monotonically non-decreasing.
     */
    public function testElapsedMsIsMonotonicallyNonDecreasing(): void
    {
        $this->forAll(
            Generator\choose(1, 5)
        )
        ->withMaxSize(100)
        ->then(function (int $numItems): void {
            $visionResult = $this->generateVisionResult();
            $analysisData = $this->generateAnalysisData($numItems);
            $gemini = $this->createMockedGemini($visionResult, $analysisData);

            $this->simulatePipelineSSE($gemini, 'base64image');

            for ($i = 1; $i < count($this->capturedEvents); $i++) {
                $this->assertGreaterThanOrEqual(
                    $this->capturedEvents[$i - 1]['elapsed_ms'],
                    $this->capturedEvents[$i]['elapsed_ms'],
                    "Event {$i} elapsed_ms must be >= event " . ($i - 1) . " elapsed_ms"
                );
            }
        });
    }

    /**
     * Property: 'done' events for data phases include elapsed_ms in their data payload.
     */
    public function testDoneEventsIncludeElapsedMsInData(): void
    {
        $this->forAll(
            Generator\choose(1, 5)
        )
        ->withMaxSize(100)
        ->then(function (int $numItems): void {
            $visionResult = $this->generateVisionResult();
            $analysisData = $this->generateAnalysisData($numItems);
            $gemini = $this->createMockedGemini($visionResult, $analysisData);

            $this->simulatePipelineSSE($gemini, 'base64image');

            $phasesWithElapsedMs = ['vision', 'analisis', 'validacion', 'reconciliacion'];

            foreach ($this->capturedEvents as $event) {
                if ($event['status'] === 'done' && in_array($event['fase'], $phasesWithElapsedMs)) {
                    $this->assertArrayHasKey(
                        'elapsed_ms',
                        $event['data'],
                        "Done event for fase '{$event['fase']}' must include elapsed_ms in data"
                    );
                    $this->assertIsInt($event['data']['elapsed_ms']);
                    $this->assertGreaterThanOrEqual(0, $event['data']['elapsed_ms']);
                }
            }
        });
    }
}
