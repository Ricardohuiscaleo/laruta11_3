<?php

/**
 * GeminiService — Verificación de fotos de despacho con Gemini API.
 *
 * Standalone PHP class (no Laravel). Follows the mi3 GeminiService pattern
 * adapted for caja3 dispatch photo verification.
 */
class GeminiService
{
    private string $apiKey;
    private string $model = 'gemini-2.5-flash-lite';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        // Try multiple sources for the API key
        $this->apiKey = $_ENV['GEMINI_API_KEY']
            ?? (getenv('GEMINI_API_KEY') ?: '');

        // Fallback: read from .env file directly
        if (empty($this->apiKey)) {
            $this->apiKey = $this->readEnvFile('GEMINI_API_KEY');
        }
    }

    // ─── Public Methods ───

    /**
     * Verify a dispatch photo against order items.
     *
     * @param string $imageBase64 Base64-encoded image data
     * @param array  $itemsPedido Array of order items [{nombre, cantidad}, ...]
     * @param string $tipoFoto   'productos' or 'bolsa'
     * @return array {aprobado: bool, puntaje: int, feedback: string}
     */
    public function verificarFotoDespacho(string $imageBase64, array $itemsPedido, string $tipoFoto): array
    {
        $fallback = [
            'aprobado' => true,
            'puntaje' => 0,
            'feedback' => '⏳ Verificación no disponible',
        ];

        try {
            $prompt = $this->buildVerificationPrompt($itemsPedido, $tipoFoto);
            $schema = $this->buildVerificationSchema();
            $result = $this->callGemini($prompt, $imageBase64, $schema);

            if ($result === null) {
                return $fallback;
            }

            // Log token usage for cost monitoring
            $tokens = 0;
            $processingMs = 0;
            if (isset($result['_tokens'])) {
                $tokens = $result['_tokens']['total'];
                error_log("[GeminiService] Tokens — prompt: {$result['_tokens']['prompt']}, response: {$result['_tokens']['candidates']}, total: {$tokens}");
                unset($result['_tokens']);
            }
            if (isset($result['_processing_ms'])) {
                $processingMs = $result['_processing_ms'];
                unset($result['_processing_ms']);
            }

            return [
                'aprobado' => (bool) ($result['aprobado'] ?? true),
                'puntaje' => (int) ($result['puntaje'] ?? 0),
                'feedback' => (string) ($result['feedback'] ?? $fallback['feedback']),
                'tokens_total' => $tokens,
                'processing_ms' => $processingMs,
            ];
        } catch (\Throwable $e) {
            error_log("[GeminiService] Exception in verificarFotoDespacho: " . $e->getMessage());
            return $fallback;
        }
    }

    // ─── Core API Call ───

    /**
     * Execute POST to Gemini generateContent endpoint with image.
     *
     * @param string $prompt      Text prompt
     * @param string $imageBase64 Base64-encoded image
     * @param array  $schema      Response schema for structured JSON
     * @param int    $timeout     Request timeout in seconds
     * @return array|null Decoded response or null on failure
     */
    private function callGemini(string $prompt, string $imageBase64, array $schema, int $timeout = 8): ?array
    {
        if (empty($this->apiKey)) {
            error_log('[GeminiService] GEMINI_API_KEY not configured');
            return null;
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 512,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $jsonPayload = json_encode($payload);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $t0 = microtime(true);
        $responseBody = curl_exec($ch);
        $elapsedMs = (int) round((microtime(true) - $t0) * 1000);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError !== '') {
            error_log("[GeminiService] cURL error: {$curlError}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("[GeminiService] HTTP {$httpCode}: " . substr((string) $responseBody, 0, 500));
            return null;
        }

        $decoded = json_decode((string) $responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('[GeminiService] Failed to decode API response JSON');
            return null;
        }

        // Extract the structured content from Gemini response
        $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if ($text === null) {
            error_log('[GeminiService] No text in response candidates');
            return null;
        }

        $parsed = json_decode(trim($text), true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            error_log('[GeminiService] Failed to parse response text as JSON: ' . substr($text, 0, 300));
            return null;
        }

        // Attach token usage and timing metadata
        $usage = $decoded['usageMetadata'] ?? [];
        $parsed['_tokens'] = [
            'prompt' => (int) ($usage['promptTokenCount'] ?? 0),
            'candidates' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total' => (int) ($usage['totalTokenCount'] ?? 0),
        ];
        $parsed['_processing_ms'] = $elapsedMs;

        return $parsed;
    }

    // ─── Prompt Builder ───

    /**
     * Build verification prompt based on photo type and order items.
     *
     * @param array  $itemsPedido Items del pedido [{nombre, cantidad}, ...]
     * @param string $tipoFoto   'productos' or 'bolsa'
     * @return string The constructed prompt
     */
    private function buildVerificationPrompt(array $itemsPedido, string $tipoFoto): string
    {
        $itemsList = '';
        foreach ($itemsPedido as $item) {
            $nombre = $item['product_name'] ?? $item['nombre'] ?? $item['name'] ?? 'Item desconocido';
            $cantidad = $item['quantity'] ?? $item['cantidad'] ?? 1;
            $recipeIngredients = $item['recipe_ingredients'] ?? [];
            $receta = $item['recipe_description'] ?? '';
            $descripcion = $item['description'] ?? '';
            
            $itemsList .= "- {$nombre} x{$cantidad}\n";
            
            // Classify ingredients by category for smart verification
            if (!empty($recipeIngredients)) {
                $visibles = [];
                $noVisibles = [];
                $packaging = [];
                foreach ($recipeIngredients as $ing) {
                    $cat = $ing['category'] ?? '';
                    $ingName = $ing['name'] ?? '';
                    if (in_array($cat, ['Packaging', 'Limpieza', 'Servicios', 'Gas'])) {
                        $packaging[] = $ingName;
                    } elseif (in_array($cat, ['Salsas', 'Condimentos'])) {
                        $noVisibles[] = $ingName;
                    } elseif (in_array($cat, ['Lácteos']) && stripos($ingName, 'Queso') === false) {
                        $noVisibles[] = $ingName;
                    } else {
                        $visibles[] = $ingName;
                    }
                }
                if (!empty($visibles)) {
                    $itemsList .= "  Ingredientes visibles: " . implode(', ', $visibles) . "\n";
                }
                if (!empty($noVisibles)) {
                    $itemsList .= "  NO visibles (dentro del pan/cocción): " . implode(', ', $noVisibles) . "\n";
                }
                if (!empty($packaging)) {
                    $itemsList .= "  Envase: " . implode(', ', $packaging) . "\n";
                }
            } elseif (!empty($receta)) {
                $itemsList .= "  Ingredientes: {$receta}\n";
            } elseif (!empty($descripcion)) {
                $itemsList .= "  Descripción: {$descripcion}\n";
            }
        }

        if ($tipoFoto === 'productos') {
            return <<<PROMPT
Eres un verificador de calidad de despacho para La Ruta 11 (food truck chileno: completos, hamburguesas, papas fritas, salchipapas, combos).

PEDIDO DEL CLIENTE:
{$itemsList}
TAREA: Analiza esta foto y verifica que los productos del pedido estén presentes.

VERIFICACIÓN OBLIGATORIA:
1. COMPARA lo que ves en la foto contra la lista del pedido. ¿Cada item del pedido es visible en la foto?
2. Si el pedido dice "Cheeseburger" y ves papas fritas, eso es un PROBLEMA — falta la hamburguesa.
3. Si el pedido dice "Completo" y ves algo volcado o de lado, eso es un PROBLEMA de orientación.
4. Si ves productos que NO están en el pedido, menciónalo como observación.
5. ¿Las cantidades coinciden? (ej: si pide 2x y solo se ve 1, es problema)

IMPORTANTE — INGREDIENTES:
- Los ingredientes marcados como "NO visibles" van dentro del pan o son de cocción. No penalizar por no verlos.
- Los ingredientes marcados como "Envase" son el packaging — verifica que el envase correcto esté presente.
- Enfócate en los ingredientes marcados como "visibles": panes, carnes, tocino, papas, bebidas, vegetales grandes.

CRITERIOS DE PUNTAJE:
- 80-100: Todos los items del pedido visibles, bien presentados, orientación correcta.
- 50-79: Se ven los items pero hay observaciones (empaque abierto, orientación dudosa).
- 0-49: Faltan items del pedido, productos incorrectos, o foto no muestra los productos.

REGLAS DE RESPUESTA:
- Sé específico: nombra qué items del pedido ves y cuáles NO ves.
- Máximo 1 oración corta en el feedback, como si le hablaras a un compañero de trabajo.
- Tono casual y amigable, NO formal ni de reporte. Ejemplos buenos:
  "Todo bien, se ve completo ✅"
  "Verifica si lleva tocino, no lo alcanzo a ver ⚠️"
  "Falta la bebida del combo ⚠️"
- Ejemplos MALOS (no hagas esto):
  "Productos: Falta el tocino laminado, que es un ingrediente visible. Retoma la foto mostrando el tocino en la hamburguesa."
  "Se observa que el pedido no incluye..."
- Emojis: ✅ si todo coincide, ⚠️ si hay algo que revisar.
- Español chileno informal y directo. Nada de "Retoma la foto mostrando X".
PROMPT;
        }

        // tipoFoto === 'bolsa'
        return <<<PROMPT
Eres un verificador de calidad de despacho para La Ruta 11 (food truck chileno).

PEDIDO DEL CLIENTE:
{$itemsList}
TAREA: Esta foto se toma DESDE ARRIBA con la bolsa ABIERTA para ver el contenido antes de sellarla. Verifica que todo esté listo para delivery.

CONTEXTO IMPORTANTE:
- La bolsa DEBE estar abierta en esta foto — es para inspeccionar el contenido antes de cerrarla.
- Estás viendo las cajas/envases desde arriba (vista cenital).
- NO penalizar porque la bolsa esté abierta.

VERIFICACIÓN:
1. ¿Las cajas/envases están CERRADOS? (tapas puestas, no abiertos)
2. ¿Los envases están en posición estable para transporte?
   - Cajas deben verse planas desde arriba (bien asentadas, no inclinadas ni volcadas)
   - Si una caja está de lado o inclinada, el contenido se puede derramar al transportar
3. ¿Se ven todos los items del pedido empacados? (cantidad de envases vs items pedidos)
4. ¿Hay bebidas si el pedido las incluye?

CRITERIOS DE PUNTAJE:
- 80-100: Envases cerrados, bien posicionados, cantidad correcta.
- 50-79: Se ven los envases pero alguno está abierto o en posición inestable.
- 0-49: Envases abiertos, volcados, o faltan items.

REGLAS DE RESPUESTA:
- Máximo 1 oración corta, como si le hablaras a un compañero de trabajo.
- Tono casual y amigable. Ejemplos buenos:
  "Cajas cerradas y bien puestas, listo para sellar ✅"
  "Cierra la caja de la hamburguesa antes de sellar ⚠️"
  "Falta la bebida del combo en la bolsa ⚠️"
- Emojis: ✅ si está bien, ⚠️ si hay algo que revisar.
- Español chileno informal y directo.
PROMPT;
    }

    // ─── Schema Builder ───

    /**
     * Build the response schema for verification results.
     *
     * @return array Schema for {aprobado: boolean, puntaje: integer, feedback: string}
     */
    private function buildVerificationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'aprobado' => [
                    'type' => 'boolean',
                ],
                'puntaje' => [
                    'type' => 'integer',
                ],
                'feedback' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['aprobado', 'puntaje', 'feedback'],
        ];
    }

    // ─── Helpers ───

    /**
     * Read a key from .env file as fallback when env vars aren't available.
     *
     * @param string $key The environment variable name to look for
     * @return string The value or empty string if not found
     */
    private function readEnvFile(string $key): string
    {
        $envPaths = [
            __DIR__ . '/../.env',
            __DIR__ . '/../../.env',
        ];

        foreach ($envPaths as $envPath) {
            if (!file_exists($envPath)) {
                continue;
            }
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines === false) {
                continue;
            }
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (strpos($line, '=') === false) {
                    continue;
                }
                [$envKey, $envValue] = explode('=', $line, 2);
                if (trim($envKey) === $key) {
                    return trim($envValue);
                }
            }
        }

        return '';
    }
}
