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

            return [
                'aprobado' => (bool) ($result['aprobado'] ?? true),
                'puntaje' => (int) ($result['puntaje'] ?? 0),
                'feedback' => (string) ($result['feedback'] ?? $fallback['feedback']),
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

        $responseBody = curl_exec($ch);
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
            $nombre = $item['nombre'] ?? $item['name'] ?? 'Item desconocido';
            $cantidad = $item['cantidad'] ?? $item['quantity'] ?? 1;
            $itemsList .= "- {$nombre} x{$cantidad}\n";
        }

        if ($tipoFoto === 'productos') {
            return <<<PROMPT
Eres un verificador de calidad para un restaurante de comida rápida chileno (La Ruta 11).
Analiza esta foto de los PRODUCTOS de un pedido delivery antes de empacar.

ITEMS DEL PEDIDO:
{$itemsList}
VERIFICA:
1. ¿Se ven los productos del pedido? ¿Están todos los items visibles?
2. ¿Las cantidades parecen correctas según lo pedido?
3. ¿Los productos están en orientación correcta (horizontal, no volcados ni de lado)?
4. ¿El empaque/presentación se ve en buen estado?

REGLAS:
- Si la foto está borrosa o muy oscura, indica que se necesita retomar.
- Si no se pueden distinguir los productos, indica que se necesita una foto más clara.
- Puntaje 80-100: todo se ve bien. 50-79: hay observaciones menores. 0-49: problemas serios.
- Sé breve y directo en el feedback (máximo 2 oraciones).
- Usa emojis al inicio del feedback: ✅ si aprobado, ⚠️ si hay problemas.
- Responde en español chileno informal.
PROMPT;
        }

        // tipoFoto === 'bolsa'
        return <<<PROMPT
Eres un verificador de calidad para un restaurante de comida rápida chileno (La Ruta 11).
Analiza esta foto de la BOLSA SELLADA de un pedido delivery listo para despacho.

ITEMS DEL PEDIDO:
{$itemsList}
VERIFICA:
1. ¿Se ve una bolsa cerrada/sellada lista para entregar?
2. ¿La bolsa parece estar en buen estado para transporte delivery?
3. ¿Se ve segura y bien cerrada para que no se abra durante el transporte?

REGLAS:
- Si la foto está borrosa o muy oscura, indica que se necesita retomar.
- Si no se ve una bolsa sellada, indica el problema.
- Puntaje 80-100: bolsa bien sellada y lista. 50-79: observaciones menores. 0-49: problemas serios.
- Sé breve y directo en el feedback (máximo 2 oraciones).
- Usa emojis al inicio del feedback: ✅ si aprobado, ⚠️ si hay problemas.
- Responde en español chileno informal.
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
