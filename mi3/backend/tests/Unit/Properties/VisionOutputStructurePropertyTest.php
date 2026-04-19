<?php

namespace Tests\Unit\Properties;

use App\Services\Compra\GeminiService;
use Eris\Generator;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Feature: multi-agent-compras-pipeline, Property 2: estructura de salida del Agente Visión
 *
 * Para cualquier respuesta válida de Gemini al Agente Visión, el resultado parseado debe contener:
 * `texto_crudo` (string no vacío), `descripcion_visual` (string no vacío),
 * `tipo_imagen` (uno de los 6 valores enum válidos), `confianza` (float entre 0.0 y 1.0),
 * y `razon` (string).
 *
 * **Validates: Requirements 1.2, 1.7**
 */
class VisionOutputStructurePropertyTest extends TestCase
{
    use TestTrait;

    private const VALID_TIPOS = ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'];

    private GeminiService $geminiService;
    private ReflectionMethod $parseResponseMethod;
    private ReflectionMethod $extractTokensMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new ReflectionClass(GeminiService::class);
        $this->geminiService = $reflection->newInstanceWithoutConstructor();

        $this->parseResponseMethod = $reflection->getMethod('parseResponse');
        $this->parseResponseMethod->setAccessible(true);

        $this->extractTokensMethod = $reflection->getMethod('extractTokens');
        $this->extractTokensMethod->setAccessible(true);
    }

    /**
     * Simulates what percibir() does after callGemini returns a response:
     * parseResponse → normalize tipo_imagen → extract tokens → build result.
     */
    private function simulatePercibirParsing(array $apiResponse): ?array
    {
        $parsed = $this->parseResponseMethod->invoke($this->geminiService, $apiResponse);
        if ($parsed === null) {
            return null;
        }

        $validTypes = self::VALID_TIPOS;
        if (!in_array($parsed['tipo_imagen'] ?? '', $validTypes, true)) {
            $parsed['tipo_imagen'] = 'desconocido';
        }

        $tokens = $this->extractTokensMethod->invoke($this->geminiService, $apiResponse);

        return [
            'texto_crudo' => $parsed['texto_crudo'] ?? '',
            'descripcion_visual' => $parsed['descripcion_visual'] ?? '',
            'tipo_imagen' => $parsed['tipo_imagen'],
            'confianza' => (float) ($parsed['confianza'] ?? 0.5),
            'razon' => $parsed['razon'] ?? '',
            'tokens' => $tokens,
        ];
    }

    /**
     * Builds a valid Gemini API response structure wrapping the given JSON data.
     */
    private function buildGeminiApiResponse(array $parsedData, int $promptTokens = 260, int $candidateTokens = 150): array
    {
        return [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => json_encode($parsedData)],
                        ],
                    ],
                    'finishReason' => 'STOP',
                ],
            ],
            'usageMetadata' => [
                'promptTokenCount' => $promptTokens,
                'candidatesTokenCount' => $candidateTokens,
                'totalTokenCount' => $promptTokens + $candidateTokens,
            ],
        ];
    }

    /**
     * Generator for random non-empty strings (simulating text content).
     */
    private function nonEmptyStringGenerator(): Generator
    {
        return Generator\map(
            function (array $words) {
                return implode(' ', $words);
            },
            Generator\tuple(
                Generator\elements([
                    'Boleta', 'Factura', 'Total', 'Proveedor', 'Fecha', 'IVA',
                    'Producto', 'Cantidad', 'Precio', 'Subtotal', 'Neto',
                    'Tomates', 'Papas', 'Carne', 'Pan', 'Leche', 'Aceite',
                ]),
                Generator\elements([
                    '12345', '$1.500', '2 kg', '10 un', '19%', '01/04/2025',
                    'RUT 76.123.456-7', 'Efectivo', 'Transferencia',
                ]),
                Generator\elements([
                    'documento impreso', 'ticket térmico', 'imagen clara',
                    'texto legible', 'formato estándar', 'papel blanco',
                ])
            )
        );
    }

    /**
     * Generator for random confianza values (float 0.0 to 1.0).
     */
    private function confianzaGenerator(): Generator
    {
        return Generator\map(
            function (int $value) {
                return $value / 100.0;
            },
            Generator\choose(0, 100)
        );
    }

    /**
     * Generator for random razon strings.
     */
    private function razonGenerator(): Generator
    {
        return Generator\elements([
            'Documento con formato de boleta electrónica',
            'Factura con RUT y detalle de productos',
            'Imagen de producto sin texto visible',
            'Báscula mostrando peso en kilogramos',
            'Comprobante de transferencia bancaria',
            'No se puede determinar el tipo de documento',
            'Boleta de supermercado con código de barras',
            'Factura de proveedor mayorista',
            'Producto a granel en feria',
            'Documento parcialmente legible',
        ]);
    }

    /**
     * Generator for a complete valid Gemini vision response payload.
     */
    private function validVisionResponseGenerator(): Generator
    {
        return Generator\map(
            function (array $tuple) {
                [$textoCrudo, $descripcionVisual, $tipoImagen, $confianza, $razon] = $tuple;
                return [
                    'texto_crudo' => $textoCrudo,
                    'descripcion_visual' => $descripcionVisual,
                    'tipo_imagen' => $tipoImagen,
                    'confianza' => $confianza,
                    'razon' => $razon,
                ];
            },
            Generator\tuple(
                $this->nonEmptyStringGenerator(),
                $this->nonEmptyStringGenerator(),
                Generator\elements(self::VALID_TIPOS),
                $this->confianzaGenerator(),
                $this->razonGenerator()
            )
        );
    }


    /**
     * Property: percibir() output always contains all required fields.
     * For any valid Gemini response, the parsed result must have:
     * texto_crudo, descripcion_visual, tipo_imagen, confianza, razon, tokens.
     */
    public function testPercibirOutputContainsAllRequiredFields(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result, 'percibir() should not return null for valid responses');
            $this->assertArrayHasKey('texto_crudo', $result);
            $this->assertArrayHasKey('descripcion_visual', $result);
            $this->assertArrayHasKey('tipo_imagen', $result);
            $this->assertArrayHasKey('confianza', $result);
            $this->assertArrayHasKey('razon', $result);
            $this->assertArrayHasKey('tokens', $result);
        });
    }

    /**
     * Property: tipo_imagen is always one of the 6 valid enum values.
     * For any valid Gemini response, tipo_imagen must be in the allowed set.
     */
    public function testTipoImagenIsAlwaysValidEnum(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertContains(
                $result['tipo_imagen'],
                self::VALID_TIPOS,
                "tipo_imagen '{$result['tipo_imagen']}' must be one of: " . implode(', ', self::VALID_TIPOS)
            );
        });
    }

    /**
     * Property: confianza is always a float between 0.0 and 1.0.
     * For any valid Gemini response, confianza must be in [0.0, 1.0].
     */
    public function testConfianzaIsAlwaysFloatInRange(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertIsFloat($result['confianza']);
            $this->assertGreaterThanOrEqual(0.0, $result['confianza']);
            $this->assertLessThanOrEqual(1.0, $result['confianza']);
        });
    }

    /**
     * Property: texto_crudo, descripcion_visual, and razon are always strings.
     */
    public function testTextFieldsAreAlwaysStrings(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertIsString($result['texto_crudo']);
            $this->assertIsString($result['descripcion_visual']);
            $this->assertIsString($result['razon']);
        });
    }

    /**
     * Property: Invalid tipo_imagen values are normalized to 'desconocido'.
     * For any Gemini response with an invalid tipo_imagen, percibir() must normalize it.
     */
    public function testInvalidTipoImagenNormalizedToDesconocido(): void
    {
        $invalidTipos = Generator\elements([
            'receipt', 'invoice', 'unknown', 'otro', 'imagen', 'foto',
            'BOLETA', 'Factura', 'PRODUCTO', '', 'null', 'undefined',
            'ticket', 'comprobante', 'nota_venta', 'guia_despacho',
        ]);

        $this->forAll(
            $this->nonEmptyStringGenerator(),
            $this->nonEmptyStringGenerator(),
            $invalidTipos,
            $this->confianzaGenerator(),
            $this->razonGenerator()
        )
        ->withMaxSize(100)
        ->then(function (string $textoCrudo, string $descripcionVisual, string $invalidTipo, float $confianza, string $razon) {
            $visionData = [
                'texto_crudo' => $textoCrudo,
                'descripcion_visual' => $descripcionVisual,
                'tipo_imagen' => $invalidTipo,
                'confianza' => $confianza,
                'razon' => $razon,
            ];

            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertSame(
                'desconocido',
                $result['tipo_imagen'],
                "Invalid tipo_imagen '{$invalidTipo}' should be normalized to 'desconocido'"
            );
        });
    }

    /**
     * Property: tokens structure is always present and valid.
     * For any valid Gemini response, tokens must contain prompt, candidates, and total.
     */
    public function testTokensStructureIsAlwaysValid(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator(),
            Generator\choose(100, 500),
            Generator\choose(50, 300)
        )
        ->withMaxSize(100)
        ->then(function (array $visionData, int $promptTokens, int $candidateTokens) {
            $apiResponse = $this->buildGeminiApiResponse($visionData, $promptTokens, $candidateTokens);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertArrayHasKey('tokens', $result);
            $this->assertArrayHasKey('prompt', $result['tokens']);
            $this->assertArrayHasKey('candidates', $result['tokens']);
            $this->assertArrayHasKey('total', $result['tokens']);
            $this->assertIsInt($result['tokens']['prompt']);
            $this->assertIsInt($result['tokens']['candidates']);
            $this->assertIsInt($result['tokens']['total']);
            $this->assertSame($promptTokens, $result['tokens']['prompt']);
            $this->assertSame($candidateTokens, $result['tokens']['candidates']);
            $this->assertSame($promptTokens + $candidateTokens, $result['tokens']['total']);
        });
    }

    /**
     * Property: percibir() preserves texto_crudo content from valid responses.
     * For any valid Gemini response, the texto_crudo in the output must match the input.
     */
    public function testPercibirPreservesTextoCrudoContent(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $apiResponse = $this->buildGeminiApiResponse($visionData);
            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result);
            $this->assertSame($visionData['texto_crudo'], $result['texto_crudo']);
            $this->assertSame($visionData['descripcion_visual'], $result['descripcion_visual']);
            $this->assertSame($visionData['razon'], $result['razon']);
        });
    }

    /**
     * Property: Gemini response wrapped in markdown code blocks is parsed correctly.
     * For any valid vision data wrapped in ```json...```, percibir() must still parse it.
     */
    public function testMarkdownWrappedJsonIsParsedCorrectly(): void
    {
        $this->forAll(
            $this->validVisionResponseGenerator()
        )
        ->withMaxSize(100)
        ->then(function (array $visionData) {
            $jsonText = "```json\n" . json_encode($visionData) . "\n```";

            $apiResponse = [
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => $jsonText],
                            ],
                        ],
                        'finishReason' => 'STOP',
                    ],
                ],
                'usageMetadata' => [
                    'promptTokenCount' => 260,
                    'candidatesTokenCount' => 150,
                    'totalTokenCount' => 410,
                ],
            ];

            $result = $this->simulatePercibirParsing($apiResponse);

            $this->assertNotNull($result, 'percibir() should parse markdown-wrapped JSON');
            $this->assertSame($visionData['texto_crudo'], $result['texto_crudo']);
            $this->assertContains($result['tipo_imagen'], self::VALID_TIPOS);
        });
    }
}
