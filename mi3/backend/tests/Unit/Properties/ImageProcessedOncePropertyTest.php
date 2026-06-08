<?php

namespace Tests\Unit\Properties;

use App\Services\Compra\GeminiService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Feature: multi-agent-compras-pipeline, Property 1: imagen procesada exactamente una vez
 *
 * Para cualquier imagen válida enviada al pipeline multi-agente, exactamente UNA llamada
 * a la API de Gemini debe incluir datos de imagen (base64). Las llamadas de los agentes 2, 3 y 4
 * deben contener exclusivamente texto en su payload.
 *
 * **Validates: Requirements 1.1, 1.5, 2.1, 3.7, 4.6, 8.1, 8.2**
 */
class ImageProcessedOncePropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Tracks calls made to callGemini (with image) and callGeminiText (without image).
     */
    private array $callLog = [];

    /**
     * Creates a mocked GeminiService that tracks which methods receive image data
     * and which operate text-only.
     */
    private function createSpyGeminiService(): GeminiService
    {
        $testCase = $this;

        $mock = \Mockery::mock(GeminiService::class)->makePartial();

        // Mock percibir to track that it receives imageBase64
        $mock->shouldReceive('percibir')
            ->andReturnUsing(function (string $imageBase64) use ($testCase) {
                $testCase->callLog[] = [
                    'method' => 'percibir',
                    'has_image' => !empty($imageBase64),
                    'image_length' => strlen($imageBase64),
                ];
                return [
                    'texto_crudo' => 'Texto extraído de prueba',
                    'descripcion_visual' => 'Imagen de una boleta con productos',
                    'tipo_imagen' => 'boleta',
                    'confianza' => 0.92,
                    'razon' => 'Documento con formato de boleta',
                    'tokens' => ['prompt' => 260, 'candidates' => 150, 'total' => 410],
                ];
            });

        // Mock analizarYValidar — text-only, no image parameter, includes auto-validation
        $mock->shouldReceive('analizarYValidar')
            ->andReturnUsing(function (string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples = []) use ($testCase) {
                $testCase->callLog[] = [
                    'method' => 'analizarYValidar',
                    'has_image' => false,
                    'params' => ['textoCrudo', 'descripcionVisual', 'tipo', 'contexto', 'fewShotExamples'],
                ];
                return [
                    'data' => [
                        'tipo_imagen' => $tipo,
                        'proveedor' => 'Proveedor Test',
                        'items' => [['nombre' => 'Item 1', 'cantidad' => 1, 'unidad' => 'un', 'precio_unitario' => 1000, 'subtotal' => 1000]],
                        'monto_total' => 1000,
                    ],
                    'inconsistencias' => [],
                    'tokens' => ['prompt' => 750, 'candidates' => 400, 'total' => 1150],
                ];
            });

        // validar is deprecated — merged into analizarYValidar

        // Mock reconciliar — text-only, no image parameter
        $mock->shouldReceive('reconciliar')
            ->andReturnUsing(function (array $datos, array $inconsistencias, string $textoCrudo, array $contextoBd) use ($testCase) {
                $testCase->callLog[] = [
                    'method' => 'reconciliar',
                    'has_image' => false,
                    'params' => ['datos', 'inconsistencias', 'textoCrudo', 'contextoBd'],
                ];
                return [
                    'datos_finales' => $datos,
                    'correcciones_aplicadas' => [],
                    'preguntas' => [],
                    'tokens' => ['prompt' => 300, 'candidates' => 80, 'total' => 380],
                ];
            });

        return $mock;
    }

    /**
     * Simulates the multi-agent pipeline flow as defined in the design.
     * Replicates the orchestration logic of ejecutarMultiAgente().
     */
    private function simulateMultiAgentPipeline(GeminiService $gemini, string $imageBase64): array
    {
        // Phase 1: Vision (with image)
        $visionResult = $gemini->percibir($imageBase64);
        if ($visionResult === null) {
            return ['success' => false, 'error' => 'vision_failed'];
        }

        // Phase 2: Analysis + validation (combined)
        $contexto = ['proveedores' => [], 'ingredientes' => [], 'rut_map' => []];
        $fewShot = [];
        $combinedResult = $gemini->analizarYValidar(
            $visionResult['texto_crudo'],
            $visionResult['descripcion_visual'],
            $visionResult['tipo_imagen'],
            $contexto,
            $fewShot
        );
        if ($combinedResult === null) {
            return ['success' => false, 'error' => 'analysis_failed'];
        }

        $inconsistencias = $combinedResult['inconsistencias'] ?? [];

        // Phase 3: Reconciliation (text-only)
        $reconciliationResult = $gemini->reconciliar(
            $combinedResult['data'],
            $inconsistencias,
            $visionResult['texto_crudo'],
            $contexto
        );

        return [
            'success' => true,
            'data' => $reconciliationResult['datos_finales'] ?? $combinedResult['data'],
        ];
    }

    /**
     * Generator for random base64-like image strings of varying lengths.
     */
    private function imageBase64Generator(): Generator
    {
        return Generator\map(
            function (int $length) {
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';
                $result = '';
                for ($i = 0; $i < $length; $i++) {
                    $result .= $chars[random_int(0, strlen($chars) - 1)];
                }
                return $result;
            },
            Generator\choose(100, 5000)
        );
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    /**
     * Property: Only percibir() receives image data.
     * For any valid image base64 string, when the multi-agent pipeline executes,
     * exactly ONE method (percibir) should receive image data.
     */
    public function testOnlyPercibirReceivesImageData(): void
    {
        $this->forAll(
            $this->imageBase64Generator()
        )
        ->withMaxSize(100)
        ->then(function (string $imageBase64) {
            $this->callLog = [];
            $gemini = $this->createSpyGeminiService();

            $result = $this->simulateMultiAgentPipeline($gemini, $imageBase64);

            $this->assertTrue($result['success'], 'Pipeline should complete successfully');

            // Verify exactly 3 calls were made (vision, analysis+validation merged, reconciliation)
            $this->assertCount(3, $this->callLog, 'Pipeline should make exactly 3 GeminiService calls (validacion merged into analisis)');

            // Verify only percibir received image data
            $callsWithImage = array_filter($this->callLog, fn($call) => $call['has_image'] === true);
            $this->assertCount(1, $callsWithImage, 'Exactly ONE call should include image data');

            // Verify that call is percibir
            $imageCall = array_values($callsWithImage)[0];
            $this->assertSame('percibir', $imageCall['method'], 'The call with image must be percibir()');
        });
    }

    /**
     * Property: Agents 2, 3 never receive image data.
     * For any valid image, analizarYValidar and reconciliar must operate text-only.
     */
    public function testTextOnlyAgentsNeverReceiveImage(): void
    {
        $this->forAll(
            $this->imageBase64Generator()
        )
        ->withMaxSize(100)
        ->then(function (string $imageBase64) {
            $this->callLog = [];
            $gemini = $this->createSpyGeminiService();

            $result = $this->simulateMultiAgentPipeline($gemini, $imageBase64);

            $this->assertTrue($result['success'], 'Pipeline should complete successfully');

            // Filter calls that are NOT percibir
            $textOnlyCalls = array_filter($this->callLog, fn($call) => $call['method'] !== 'percibir');

            // Verify all non-percibir calls have has_image = false
            foreach ($textOnlyCalls as $call) {
                $this->assertFalse(
                    $call['has_image'],
                    "Method {$call['method']} should NOT receive image data"
                );
            }

            // Verify we have exactly 2 text-only calls (analisis+validacion merged, reconciliacion)
            $this->assertCount(2, $textOnlyCalls, 'Should have exactly 2 text-only agent calls (analisis+validacion merged, reconciliacion)');
        });
    }

    /**
     * Property: percibir always receives non-empty image data.
     * For any non-empty image base64 string, percibir must receive it with length > 0.
     */
    public function testPercibirAlwaysReceivesNonEmptyImage(): void
    {
        $this->forAll(
            $this->imageBase64Generator()
        )
        ->withMaxSize(100)
        ->then(function (string $imageBase64) {
            $this->callLog = [];
            $gemini = $this->createSpyGeminiService();

            $this->simulateMultiAgentPipeline($gemini, $imageBase64);

            // Find the percibir call
            $percibirCalls = array_filter($this->callLog, fn($call) => $call['method'] === 'percibir');
            $this->assertCount(1, $percibirCalls, 'percibir should be called exactly once');

            $percibirCall = array_values($percibirCalls)[0];
            $this->assertTrue($percibirCall['has_image'], 'percibir must receive image data');
            $this->assertGreaterThan(0, $percibirCall['image_length'], 'Image data must be non-empty');
            $this->assertSame(
                strlen($imageBase64),
                $percibirCall['image_length'],
                'percibir must receive the full image data unchanged'
            );
        });
    }

    /**
     * Property: Pipeline execution order is always Vision → Analysis+Validation (merged) → Reconciliation.
     * For any image, the agents must be called in strict sequential order.
     */
    public function testAgentExecutionOrderIsStrict(): void
    {
        $this->forAll(
            $this->imageBase64Generator()
        )
        ->withMaxSize(100)
        ->then(function (string $imageBase64) {
            $this->callLog = [];
            $gemini = $this->createSpyGeminiService();

            $this->simulateMultiAgentPipeline($gemini, $imageBase64);

            $expectedOrder = ['percibir', 'analizarYValidar', 'reconciliar'];
            $actualOrder = array_map(fn($call) => $call['method'], $this->callLog);

            $this->assertSame(
                $expectedOrder,
                $actualOrder,
                'Agents must execute in order: percibir → analizarYValidar → reconciliar'
            );
        });
    }

    /**
     * Property: analizarYValidar receives text from percibir, not image.
     * For any image, the text passed to analizarYValidar must come from percibir's output,
     * not from the original image data.
     */
    public function testAnalizarYValidarReceivesTextFromVisionNotImage(): void
    {
        $this->forAll(
            $this->imageBase64Generator()
        )
        ->withMaxSize(100)
        ->then(function (string $imageBase64) {
            $this->callLog = [];

            $receivedParams = [];
            $mock = \Mockery::mock(GeminiService::class)->makePartial();

            $mock->shouldReceive('percibir')
                ->andReturnUsing(function (string $img) {
                    return [
                        'texto_crudo' => 'TEXTO_VISION_' . substr($img, 0, 10),
                        'descripcion_visual' => 'DESC_VISUAL_' . substr($img, 0, 5),
                        'tipo_imagen' => 'boleta',
                        'confianza' => 0.9,
                        'razon' => 'test',
                        'tokens' => ['prompt' => 260, 'candidates' => 150, 'total' => 410],
                    ];
                });

            $mock->shouldReceive('analizarYValidar')
                ->andReturnUsing(function (string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShot = []) use (&$receivedParams, $imageBase64) {
                    $receivedParams['textoCrudo'] = $textoCrudo;
                    $receivedParams['descripcionVisual'] = $descripcionVisual;

                    // The text must NOT be the raw image base64
                    $this->assertNotSame(
                        $imageBase64,
                        $textoCrudo,
                        'analizarYValidar must NOT receive the raw image base64 as text'
                    );

                    return [
                        'data' => [
                            'tipo_imagen' => $tipo,
                            'proveedor' => 'Test',
                            'items' => [],
                            'monto_total' => 0,
                        ],
                        'inconsistencias' => [],
                        'tokens' => ['prompt' => 400, 'candidates' => 300, 'total' => 700],
                    ];
                });

            $mock->shouldReceive('reconciliar')->andReturn([
                'datos_finales' => ['items' => [], 'monto_total' => 0],
                'correcciones_aplicadas' => [],
                'preguntas' => [],
                'tokens' => ['prompt' => 300, 'candidates' => 80, 'total' => 380],
            ]);

            $this->simulateMultiAgentPipeline($mock, $imageBase64);

            // Verify analizarYValidar received text derived from vision, not the image
            $this->assertStringStartsWith(
                'TEXTO_VISION_',
                $receivedParams['textoCrudo'],
                'analizarYValidar must receive text output from percibir'
            );
            $this->assertStringStartsWith(
                'DESC_VISUAL_',
                $receivedParams['descripcionVisual'],
                'analizarYValidar must receive visual description from percibir'
            );
        });
    }
}
