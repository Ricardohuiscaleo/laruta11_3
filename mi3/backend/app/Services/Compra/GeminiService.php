<?php

declare(strict_types=1);

namespace App\Services\Compra;

use App\Enums\IngredientCategory;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private string $model;
    private string $apiKey;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct()
    {
        $this->apiKey = (string) env('GOOGLE_API_KEY', env('google_api_key', ''));
        $this->model = (string) env('GEMINI_MODEL', 'gemini-3-flash-preview');

        if (empty($this->apiKey)) {
            Log::warning('[GeminiService] GOOGLE_API_KEY not configured — all calls will fail');
        } else {
            Log::debug("[GeminiService] Initialized with model={$this->model}");
        }
    }

    // ─── Public Methods ───

    /**
     * Classify image type using Gemini multimodal.
     *
     * @return array{tipo_imagen: string, confianza: float, razon: string, tokens: array}|null
     */
    public function clasificar(string $imageBase64): ?array
    {
        $prompt = $this->buildClassificationPrompt();
        $schema = $this->buildClassificationSchema();

        $response = $this->callGemini($prompt, $imageBase64, $schema, 8, 256);

        if ($response === null) {
            return null;
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }

        // Normalize invalid tipo_imagen
        $validTypes = ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'];
        if (!in_array($parsed['tipo_imagen'] ?? '', $validTypes, true)) {
            $parsed['tipo_imagen'] = 'desconocido';
        }

        $tokens = $this->extractTokens($response);

        return [
            'tipo_imagen' => $parsed['tipo_imagen'],
            'confianza' => (float) ($parsed['confianza'] ?? 0.5),
            'razon' => $parsed['razon'] ?? '',
            'tokens' => $tokens,
        ];
    }

    /**
     * Analyze image with type-specific prompt and context.
     *
     * @return array{data: array, tokens: array}|null
     */
    public function analizar(string $imageBase64, string $tipo, array $contexto): ?array
    {
        $prompt = $this->buildAnalysisPrompt($tipo, $contexto);
        $schema = $this->buildExtractionSchema();

        $response = $this->callGemini($prompt, $imageBase64, $schema, 20, 2048);

        if ($response === null) {
            return null;
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }

        $parsed = $this->normalizeAmounts($parsed);
        $tokens = $this->extractTokens($response);

        return [
            'data' => $parsed,
            'tokens' => $tokens,
        ];
    }

    // ─── Core API Call ───

    /**
     * Execute POST to Gemini generateContent endpoint.
     */
    private function callGemini(string $prompt, string $imageBase64, array $schema, int $timeout, int $maxOutputTokens = 2048): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiService] GOOGLE_API_KEY not configured');
            return null;
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";
        $imageSizeKb = (int) round(strlen($imageBase64) * 3 / 4 / 1024);

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['inline_data' => ['mime_type' => 'image/jpeg', 'data' => $imageBase64]],
                        ['text' => $prompt],
                    ],
                ],
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_CIVIC_INTEGRITY', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $jsonPayload = json_encode($payload);

        Log::info("[GeminiService] callGemini model={$this->model} timeout={$timeout}s image={$imageSizeKb}KB maxTokens={$maxOutputTokens}");

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
            Log::error("[GeminiService] cURL error after {$elapsedMs}ms (model={$this->model}): {$curlError}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Log::error("[GeminiService] HTTP {$httpCode} after {$elapsedMs}ms (model={$this->model}): " . substr((string) $responseBody, 0, 800));
            return null;
        }

        $decoded = json_decode((string) $responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("[GeminiService] Failed to decode JSON after {$elapsedMs}ms: " . substr((string) $responseBody, 0, 300));
            return null;
        }

        Log::info("[GeminiService] OK {$elapsedMs}ms tokens=" . ($decoded['usageMetadata']['totalTokenCount'] ?? '?'));

        return $decoded;
    }

    // ─── New Multi-Agent Methods ───

    /**
     * Agente 1 (Visión): Multimodal call to extract text + visual description + classification.
     *
     * @return array{texto_crudo: string, descripcion_visual: string, tipo_imagen: string, confianza: float, razon: string, tokens: array}|null
     */
    public function percibir(string $imageBase64): ?array
    {
        $prompt = $this->buildVisionPrompt();
        $schema = $this->buildVisionSchema();

        // Attempt with retry (1 retry on failure). Higher maxOutputTokens to avoid
        // truncation mid-JSON when texto_crudo is long (e.g. Mercado Pago receipts).
        $response = $this->callGemini($prompt, $imageBase64, $schema, 20, 8192);
        if ($response === null) {
            Log::warning('[GeminiService] percibir: first attempt failed, retrying with longer timeout...');
            usleep(500_000); // 500ms backoff
            $response = $this->callGemini($prompt, $imageBase64, $schema, 30, 8192);
            if ($response === null) {
                Log::error('[GeminiService] percibir: retry also failed');
                return null;
            }
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            Log::error('[GeminiService] percibir: parseResponse returned null');
            return null;
        }

        // Normalize tipo_imagen
        $validTypes = ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'];
        if (!in_array($parsed['tipo_imagen'] ?? '', $validTypes, true)) {
            $parsed['tipo_imagen'] = 'desconocido';
        }

        $tokens = $this->extractTokens($response);

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
     * Agente 2 (Análisis): Text-only analysis that structures data from extracted text.
     *
     * @return array{data: array, tokens: array}|null
     */
    public function analizarTexto(string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples = []): ?array
    {
        $prompt = $this->buildTextAnalysisPrompt($textoCrudo, $descripcionVisual, $tipo, $contexto, $fewShotExamples);
        $schema = $this->buildExtractionSchema();

        $response = $this->callGeminiText($prompt, $schema, 20, 2048);
        if ($response === null) {
            Log::warning('[GeminiService] analizarTexto: first attempt failed, retrying...');
            usleep(500_000);
            $response = $this->callGeminiText($prompt, $schema, 30, 2048);
            if ($response === null) {
                Log::error('[GeminiService] analizarTexto: retry also failed');
                return null;
            }
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            Log::error('[GeminiService] analizarTexto: parseResponse returned null after retry');
            return null;
        }
        $parsed = $this->normalizeAmounts($parsed);
        $tokens = $this->extractTokens($response);

        return ['data' => $parsed, 'tokens' => $tokens];
    }

    /**
     * Agente 3 (Validación): Text-only validation of extracted data coherence.
     *
     * @return array{datos_validados: array, inconsistencias: array, tokens: array}|null
     */
    public function validar(array $datosExtraidos, array $contextoBd): ?array
    {
        $prompt = $this->buildValidationPrompt($datosExtraidos, $contextoBd);
        $schema = $this->buildValidationSchema();
        $response = $this->callGeminiText($prompt, $schema, 8, 1024);
        if ($response === null) {
            return null;
        }
        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }
        $tokens = $this->extractTokens($response);

        return [
            'datos_validados' => $parsed['datos_validados'] ?? $datosExtraidos,
            'inconsistencias' => $parsed['inconsistencias'] ?? [],
            'tokens' => $tokens,
        ];
    }

    /**
     * Agente 2+3 combinado: Análisis + Validación en 1 llamada.
     * Extrae datos estructurados y auto-valida en un solo paso.
     *
     * @return array{data: array, inconsistencias: array, tokens: array}|null
     */
    public function analizarYValidar(string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples = []): ?array
    {
        $prompt = $this->buildSelfValidatingAnalysisPrompt($textoCrudo, $descripcionVisual, $tipo, $contexto, $fewShotExamples);
        $schema = $this->buildExtractionWithValidationSchema();

        $response = $this->callGeminiText($prompt, $schema, 20, 3072);
        if ($response === null) {
            Log::warning('[GeminiService] analizarYValidar: first attempt failed, retrying...');
            usleep(500_000);
            $response = $this->callGeminiText($prompt, $schema, 30, 3072);
            if ($response === null) {
                Log::error('[GeminiService] analizarYValidar: retry also failed');
                return null;
            }
        }

        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            Log::error('[GeminiService] analizarYValidar: parseResponse returned null');
            return null;
        }

        // Strip validation fields before normalizeAmounts
        $inconsistencias = $parsed['inconsistencias'] ?? [];
        unset($parsed['inconsistencias']);
        $parsed = $this->normalizeAmounts($parsed);
        $tokens = $this->extractTokens($response);

        return ['data' => $parsed, 'inconsistencias' => $inconsistencias, 'tokens' => $tokens];
    }

    /**
     * Agente 4 (Reconciliación): Text-only reconciliation of inconsistencies.
     *
     * @return array{datos_finales: array, correcciones_aplicadas: array, preguntas: array, tokens: array}|null
     */
    public function reconciliar(array $datos, array $inconsistencias, string $textoCrudo, array $contextoBd): ?array
    {
        if (empty($inconsistencias)) {
            return [
                'datos_finales' => $datos,
                'correcciones_aplicadas' => [],
                'preguntas' => [],
                'tokens' => ['prompt' => 0, 'candidates' => 0, 'total' => 0],
            ];
        }

        $prompt = $this->buildReconciliationPrompt($datos, $inconsistencias, $textoCrudo, $contextoBd);
        $schema = $this->buildReconciliationSchema();
        $response = $this->callGeminiText($prompt, $schema, 8, 1024);
        if ($response === null) {
            return null;
        }
        $parsed = $this->parseResponse($response);
        if ($parsed === null) {
            return null;
        }
        $tokens = $this->extractTokens($response);

        return [
            'datos_finales' => $parsed['datos_finales'] ?? $datos,
            'correcciones_aplicadas' => $parsed['correcciones_aplicadas'] ?? [],
            'preguntas' => $parsed['preguntas'] ?? [],
            'tokens' => $tokens,
        ];
    }

    // ─── Core API Call (text-only) ───

    /**
     * Execute POST to Gemini generateContent endpoint WITHOUT image (text-only).
     */
    private function callGeminiText(string $prompt, array $schema, int $timeout, int $maxOutputTokens = 2048): ?array
    {
        if (empty($this->apiKey)) {
            Log::error('[GeminiService] GOOGLE_API_KEY not configured');
            return null;
        }

        $url = "{$this->baseUrl}/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt],
                    ],
                ],
            ],
            'safetySettings' => [
                ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_ONLY_HIGH'],
                ['category' => 'HARM_CATEGORY_CIVIC_INTEGRITY', 'threshold' => 'BLOCK_ONLY_HIGH'],
            ],
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                'responseMimeType' => 'application/json',
                'responseSchema' => $schema,
            ],
        ];

        $jsonPayload = json_encode($payload);
        $promptSizeKb = (int) round(strlen($jsonPayload) / 1024);

        Log::info("[GeminiService] callGeminiText model={$this->model} timeout={$timeout}s prompt={$promptSizeKb}KB maxTokens={$maxOutputTokens}");

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
            Log::error("[GeminiService] callGeminiText cURL error after {$elapsedMs}ms (model={$this->model}): {$curlError}");
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Log::error("[GeminiService] callGeminiText HTTP {$httpCode} after {$elapsedMs}ms (model={$this->model}): " . substr((string) $responseBody, 0, 800));
            return null;
        }

        $decoded = json_decode((string) $responseBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error("[GeminiService] callGeminiText failed to decode JSON after {$elapsedMs}ms (model={$this->model}): " . substr((string) $responseBody, 0, 300));
            return null;
        }

        $finishReason = $decoded['candidates'][0]['finishReason'] ?? null;
        if ($finishReason !== null && $finishReason !== 'STOP') {
            Log::warning("[GeminiService] callGeminiText non-STOP finishReason={$finishReason} after {$elapsedMs}ms (model={$this->model})");
        }

        Log::info("[GeminiService] callGeminiText OK {$elapsedMs}ms tokens=" . ($decoded['usageMetadata']['totalTokenCount'] ?? '?'));

        return $decoded;
    }

    // ─── Response Parsing ───

    /**
     * Extract JSON data from Gemini response.
     */
    private function parseResponse(array $response): ?array
    {
        if (empty($response['candidates'])) {
            $blockReason = $response['promptFeedback']['blockReason'] ?? 'unknown';
            Log::error("[GeminiService] No candidates in response. blockReason={$blockReason} response=" . substr(json_encode($response), 0, 500));
            return null;
        }

        $parts = $response['candidates'][0]['content']['parts'] ?? [];
        if (empty($parts)) {
            Log::error('[GeminiService] No parts in candidate: ' . substr(json_encode($response['candidates'][0]), 0, 500));
            return null;
        }

        $finishReason = $response['candidates'][0]['finishReason'] ?? 'UNKNOWN';
        $isTruncated = in_array($finishReason, ['MAX_TOKENS', 'LENGTH'], true);

        // Iterate all parts: skip thought parts, extract JSON from text parts
        // Gemini 3.x models may include thinking/thought parts before the JSON output
        foreach ($parts as $i => $part) {
            if (!empty($part['thought'])) {
                continue;
            }
            $text = trim($part['text'] ?? '');
            if ($text === '') {
                continue;
            }

            // Try direct JSON decode
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Robust extraction: find first '{' and last '}'
            $firstBrace = strpos($text, '{');
            $lastBrace = strrpos($text, '}');
            if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
                $jsonText = substr($text, $firstBrace, $lastBrace - $firstBrace + 1);
                $decoded = json_decode($jsonText, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            // Truncated response: try to recover by closing open string + JSON
            if ($isTruncated && $firstBrace !== false) {
                $partial = substr($text, $firstBrace);
                // Strip trailing comma if any, then close unclosed string and object
                $partial = rtrim($partial, ",\n\r\t ");
                if (substr($partial, -1) !== '"' && str_ends_with($partial, '\\')) {
                    $partial = substr($partial, 0, -1);
                }
                if (preg_match('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*$/', $partial)) {
                    $partial .= '"';
                }
                $partial .= '}';
                $decoded = json_decode($partial, true);
                if (json_decode($partial) !== null && is_array($decoded)) {
                    Log::warning("[GeminiService] parseResponse recovered from truncation (part[{$i}], finishReason={$finishReason})");
                    return $decoded;
                }
            }

            // Fallback: markdown ```json...``` blocks
            if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
                $jsonText = trim($matches[1]);
                $decoded = json_decode($jsonText, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            Log::warning("[GeminiService] parseResponse part[{$i}] not valid JSON: " . substr($text, 0, 200));
        }

        Log::error('[GeminiService] Failed to parse JSON from any part. parts=' . count($parts) . ' finishReason=' . $finishReason);
        return null;
    }

    /**
     * Extract token usage metadata from response.
     */
    private function extractTokens(array $response): array
    {
        $usage = $response['usageMetadata'] ?? [];
        return [
            'prompt' => (int) ($usage['promptTokenCount'] ?? 0),
            'candidates' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'total' => (int) ($usage['totalTokenCount'] ?? 0),
        ];
    }

    // ─── Amount Normalization ───

    /**
     * Normalize monetary amounts to integers (Chilean pesos).
     * Copied from AnalisisService::normalizeAmounts().
     */
    private function normalizeAmounts(array $data): array
    {
        $isBascula = ($data['tipo_imagen'] ?? '') === 'bascula';
        if ($isBascula) {
            if (isset($data['monto_total']) && is_numeric($data['monto_total']) && $data['monto_total'] < 1000) {
                $data['monto_total'] = (int) ($data['monto_total'] * 100);
            }
            if (!empty($data['items'])) {
                foreach ($data['items'] as &$item) {
                    if (isset($item['precio_unitario']) && is_numeric($item['precio_unitario']) && $item['precio_unitario'] < 200) {
                        $item['precio_unitario'] = (int) ($item['precio_unitario'] * 100);
                    }
                    if (isset($item['subtotal']) && is_numeric($item['subtotal']) && $item['subtotal'] < 1000) {
                        $item['subtotal'] = (int) ($item['subtotal'] * 100);
                    }
                }
                unset($item);
            }
        }

        foreach (['monto_neto', 'iva', 'otros_impuestos', 'monto_total'] as $field) {
            if (isset($data[$field]) && is_numeric($data[$field])) {
                $data[$field] = (int) round((float) $data[$field]);
            }
        }

        $total = $data['monto_total'] ?? 0;
        $iva = $data['iva'] ?? 0;
        $neto = $data['monto_neto'] ?? 0;
        $otrosImpuestos = $data['otros_impuestos'] ?? 0;

        // Factura con impuestos adicionales (ICA, etc.): Total = Neto + IVA + Otros Impuestos
        if ($total > 0 && $otrosImpuestos > 0) {
            $correctNeto = $total - $iva - $otrosImpuestos;
            if ($neto !== $correctNeto) {
                $data['monto_neto'] = $correctNeto;
            }
        }
        // Boletas chilenas: Total SIEMPRE es IVA incluido.
        // Si Gemini devolvió monto_total e iva, asegurar que monto_neto = total - iva (no al revés).
        elseif ($total > 0 && $iva > 0) {
            $correctNeto = $total - $iva;
            // Si monto_neto es mayor que monto_total, Gemini trató el total como neto — corregir
            if ($neto > $total || $neto === 0) {
                $data['monto_neto'] = $correctNeto;
            }
            // Si monto_neto + iva > monto_total (Gemini sumó IVA al total), corregir
            if ($neto > 0 && ($neto + $iva) > ($total * 1.01)) {
                $data['monto_neto'] = $correctNeto;
            }
        } elseif ($total > 0 && $iva === 0 && $neto === 0) {
            // No hay IVA explícito: calcular estándar 19%
            $data['monto_neto'] = (int) round($total / 1.19);
            $data['iva'] = $total - $data['monto_neto'];
        }

        if (!empty($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['precio_unitario']) && is_numeric($item['precio_unitario'])) {
                    $item['precio_unitario'] = (int) round((float) $item['precio_unitario']);
                }
                if (isset($item['subtotal']) && is_numeric($item['subtotal'])) {
                    $item['subtotal'] = (int) round((float) $item['subtotal']);
                }
                $descuento = (int) abs((float) ($item['descuento'] ?? 0));
                if ($descuento > 0 && isset($item['subtotal'])) {
                    $item['subtotal'] -= $descuento;
                    $cantidad = max(1, (float) ($item['cantidad'] ?? 1));
                    $item['precio_unitario'] = (int) round($item['subtotal'] / $cantidad);
                    $item['notas_descuento'] = "Descuento -\${$descuento} aplicado";
                }
                unset($item['descuento']);
            }
            unset($item);
        }

        return $data;
    }

    // ─── Schema Builders ───

    private function buildClassificationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo_imagen' => [
                    'type' => 'string',
                    'enum' => ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'],
                ],
                'confianza' => [
                    'type' => 'number',
                ],
                'razon' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['tipo_imagen', 'confianza', 'razon'],
        ];
    }

    private function buildExtractionSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo_imagen' => ['type' => 'string'],
                'proveedor' => ['type' => 'string'],
                'rut_proveedor' => ['type' => 'string'],
                'fecha' => ['type' => 'string'],
                'metodo_pago' => [
                    'type' => 'string',
                    'enum' => ['cash', 'transfer', 'card', 'credit'],
                ],
                'tipo_compra' => [
                    'type' => 'string',
                    'enum' => ['ingredientes', 'insumos', 'equipamiento', 'otros'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nombre' => ['type' => 'string'],
                            'cantidad' => ['type' => 'number'],
                            'unidad' => ['type' => 'string'],
                            'precio_unitario' => ['type' => 'integer'],
                            'subtotal' => ['type' => 'integer'],
                            'descuento' => ['type' => 'integer'],
                            'empaque_detalle' => ['type' => 'string'],
                            'categoria_sugerida' => [
                                'type' => 'string',
                                'enum' => array_merge(IngredientCategory::VALID_CATEGORIES, ['Sin categoría']),
                            ],
                        ],
                        'required' => ['nombre', 'cantidad', 'unidad', 'precio_unitario', 'subtotal'],
                    ],
                ],
                'monto_neto' => ['type' => 'integer'],
                'iva' => ['type' => 'integer'],
                'otros_impuestos' => ['type' => 'integer'],
                'monto_total' => ['type' => 'integer'],
                'peso_bascula' => ['type' => 'number'],
                'unidad_bascula' => ['type' => 'string'],
                'notas_ia' => ['type' => 'string'],
            ],
            'required' => ['tipo_imagen', 'items', 'monto_total'],
        ];
    }

    /**
     * Schema combinado: extracción + auto-validación.
     * inconsistencies es opcional — solo se incluye si hay errores.
     */
    private function buildExtractionWithValidationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'tipo_imagen' => ['type' => 'string'],
                'proveedor' => ['type' => 'string'],
                'rut_proveedor' => ['type' => 'string'],
                'fecha' => ['type' => 'string'],
                'metodo_pago' => [
                    'type' => 'string',
                    'enum' => ['cash', 'transfer', 'card', 'credit'],
                ],
                'tipo_compra' => [
                    'type' => 'string',
                    'enum' => ['ingredientes', 'insumos', 'equipamiento', 'otros'],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'nombre' => ['type' => 'string'],
                            'cantidad' => ['type' => 'number'],
                            'unidad' => ['type' => 'string'],
                            'precio_unitario' => ['type' => 'integer'],
                            'subtotal' => ['type' => 'integer'],
                            'descuento' => ['type' => 'integer'],
                            'empaque_detalle' => ['type' => 'string'],
                            'categoria_sugerida' => [
                                'type' => 'string',
                                'enum' => array_merge(IngredientCategory::VALID_CATEGORIES, ['Sin categoría']),
                            ],
                        ],
                        'required' => ['nombre', 'cantidad', 'unidad', 'precio_unitario', 'subtotal'],
                    ],
                ],
                'monto_neto' => ['type' => 'integer'],
                'iva' => ['type' => 'integer'],
                'otros_impuestos' => ['type' => 'integer'],
                'monto_total' => ['type' => 'integer'],
                'peso_bascula' => ['type' => 'number'],
                'unidad_bascula' => ['type' => 'string'],
                'notas_ia' => ['type' => 'string'],
                'inconsistencias' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'campo' => ['type' => 'string'],
                            'valor_actual' => ['type' => 'string'],
                            'valor_esperado' => ['type' => 'string'],
                            'severidad' => ['type' => 'string', 'enum' => ['error', 'advertencia']],
                            'descripcion' => ['type' => 'string'],
                        ],
                        'required' => ['campo', 'valor_actual', 'valor_esperado', 'severidad', 'descripcion'],
                    ],
                ],
            ],
            'required' => ['tipo_imagen', 'items', 'monto_total'],
        ];
    }

    // ─── Prompt Builders ───

    private function buildClassificationPrompt(): string
    {
        return <<<'PROMPT'
Observa esta imagen y clasifica su tipo.

Tipos posibles:
- "boleta": documento de venta con RUT, productos, total (supermercado, tienda)
- "factura": documento tributario formal con RUT emisor/receptor, IVA, detalle de items
- "producto": foto de un producto físico (caja, saco, bolsa, bandeja de ingrediente)
- "bascula": foto de báscula/balanza digital mostrando peso en display
- "transferencia": comprobante de transferencia bancaria o Mercado Pago
- "desconocido": no se puede determinar

Pistas:
- Si hay textos con RUT (XX.XXX.XXX-X), montos ($), "BOLETA" o "FACTURA" → boleta o factura
- Si se ven productos físicos (cajas, sacos, bolsas, bandejas) sin texto de montos → producto
- Si se ve una báscula/balanza digital con displays numéricos → bascula
- Si hay textos con "Transferencia", "Mercado Pago", "Comprobante" → transferencia
- "FACTURA ELECTRÓNICA" en texto → factura (no boleta)

Responde con tipo_imagen, confianza (0.0-1.0) y razon (breve explicación).
PROMPT;
    }

    private function buildAnalysisPrompt(string $tipo, array $contexto): string
    {
        return match ($tipo) {
            'boleta' => $this->promptBoleta($contexto),
            'factura' => $this->promptFactura($contexto),
            'producto' => $this->promptProducto($contexto),
            'bascula' => $this->promptBascula($contexto),
            'transferencia' => $this->promptTransferencia($contexto),
            default => $this->promptGeneral($contexto),
        };
    }

    // ─── Helper: format context for prompts ───

    private function formatSuppliers(array $contexto): string
    {
        $suppliers = $contexto['suppliers'] ?? [];
        return !empty($suppliers) ? implode(', ', array_slice($suppliers, 0, 15)) : '';
    }

    private function formatProducts(array $contexto): string
    {
        $products = $contexto['products'] ?? [];
        return !empty($products) ? implode(', ', array_slice($products, 0, 25)) : '';
    }

    private function formatRutMap(array $contexto): string
    {
        $rutMap = $contexto['rut_map'] ?? [];
        if (empty($rutMap)) {
            return '';
        }
        $lines = [];
        foreach ($rutMap as $rut => $nombre) {
            $lines[] = "RUT {$rut} = {$nombre}";
        }
        return implode("\n", $lines);
    }

    private function jsonFormat(): string
    {
        return <<<'JSON'
{
  "tipo_imagen": "boleta|factura|producto|bascula|transferencia",
  "proveedor": "nombre",
  "rut_proveedor": "XX.XXX.XXX-Y o null",
  "fecha": "YYYY-MM-DD",
  "metodo_pago": "cash|transfer|card|credit",
  "tipo_compra": "ingredientes|insumos|equipamiento|otros",
  "items": [{"nombre": "...", "cantidad": N, "unidad": "kg|unidad|g|L", "precio_unitario": N, "subtotal": N, "descuento": 0, "empaque_detalle": null, "categoria_sugerida": "Carnes|Vegetales|..."}],
  "monto_neto": N, "iva": N, "otros_impuestos": N, "monto_total": N,
  "peso_bascula": null, "unidad_bascula": null,
  "notas_ia": "observaciones"
}
JSON;
    }

    // ─── Type-specific prompts (adapted from AnalisisService for Gemini) ───

    private function promptBoleta(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);
        $patterns = implode("\n", array_slice($contexto['patterns'] ?? [], 0, 10));

        return <<<PROMPT
Analiza esta imagen de boleta de compra chilena y extrae datos estructurados.

REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X \$4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN \$4790 c/u")
- Cada código de barras (78...) = 1 producto separado. NUNCA fusionar.
- Descuentos (OFERTA SEMANA, DESCTO CONVENI) van en campo "descuento" como valor positivo
- Después de "TOTAL" NO hay productos
- "TARJETA DE DEBITO" o "VENTA DEBITO" → metodo_pago = "card"
- Montos en pesos chilenos enteros (sin decimales)
- IMPORTANTE IVA EN BOLETAS CHILENAS: El "Total" de una boleta SIEMPRE es IVA INCLUIDO. Si la boleta dice "El IVA de esta boleta es 3.521", ese IVA ya está DENTRO del total, NO se suma. Entonces: monto_total = Total (tal cual), iva = el valor informado, monto_neto = monto_total - iva. NUNCA hagas monto_total = total + iva.
- Si no hay IVA explícito: monto_neto = round(total/1.19), iva = total - monto_neto

RUTs conocidos:
81.201.000-K = Jumbo/Santa Isabel (Cencosud)
81.537.600-5 = Unimarc (Rendic/SMU)
{$rutMap}

Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}

EQUIVALENCIAS DE PACKAGING (supermercado):
- "PAN DE COMPLETO XL" o "PAN COMPLETO XL": se vende por bolsa. cantidad=1 por bolsa, unidad="unidad".
- "PAN DE HAMBURGUESA": se vende por bolsa. cantidad=1 por bolsa, unidad="unidad".
- Salsas en botella (SALSA CHICKEN DIPP, SALSA BBQ, etc.): cantidad=1, unidad="unidad". Son botellas individuales.
- "BIG MONTINA" o "SALCHICHA POLLO SURENA BIG MONTINA" (cualquier variante): 10 unidades por paquete. DEBES multiplicar: si dice "3 X" → cantidad=30, unidad="unidad", precio_unitario=precio_total/30, empaque_detalle="3 paquetes × 10u c/u".
- "LAMINADO DE TOCINO": 1 unidad = 50g = 0.05 kg. Si dice "5 X" → cantidad=0.25, unidad="kg".

REGLA FUNDAMENTAL: Para productos multi-unidad, cantidad DEBE reflejar el número total de unidades individuales. Hacé la multiplicación vos mismo. NUNCA dejes paquetes sin multiplicar.

{$patterns}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptFactura(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Analiza esta imagen de factura de proveedor chilena y extrae datos estructurados.

REGLAS FACTURA CHILENA:
- PROVEEDOR = empresa EMISORA (arriba/encabezado), NO el destinatario
- "SEÑORES" o "CLIENTE" = COMPRADOR (La Ruta 11, Ricardo Huiscaleo) — NO es proveedor
- "Yumbel", "Arica" son direcciones del comprador, NO proveedores
- RUT del proveedor está en el encabezado, cerca del nombre de la empresa emisora
- URLs/dominios identifican al proveedor: ariztiaatunegocio.cl → Ariztía, agrosuper.cl → Agrosuper

IMPUESTOS EN FACTURAS CHILENAS — DISTINGUE SEGÚN TIPO:

FACTURA MAYORISTA (Agrosuper, Ariztía, Super Pollo, Vanni, etc.):
- Total = Neto + IVA + otros_impuestos (ICA si aplica).
- IVA (19%): calculado sobre Neto.
- ICA (5%): solo carnicerías, en "otros_impuestos".
- Precios unitarios visibles son NETOS (sin IVA).

FACTURA DE SUPERMERCADO (Cencosud/Jumbo, Santa Isabel, Unimarc, etc.):
- Es como BOLETA pero formato factura (tiene RUT).
- Precios en el detalle INCLUYEN IVA (igual que boleta).
- Neto = Total / 1.19. IVA = Total - Neto.
- Extrae NETO, IVA y TOTAL tal como aparecen.

EMPAQUE EN FACTURAS MAYORISTAS:
- "SALCHICHA BIG MONT 800G 10U 8X1" CANT=2 → 10×8×2 = 160 unidades
- "NNu" = unidades/paquete, "NNxN" = paquetes/caja, CANT = bultos comprados
- nombre: LIMPIO sin empaque. precio_unitario: subtotal/cantidad_total
- empaque_detalle: "10u/paq × 8paq/caja × 2 cajas = 160 unidades"

FACTURAS DE SUPERMERCADO (Cencosud/Jumbo, Santa Isabel, Unimarc, etc.):
- Cuando veas formato CONSUMIDOR tipo "3 X \$4.890" (cantidad × precio unitario), NO es formato mayorista.
- cantidad = número de paquetes × unidades_por_paquete (DEBES hacer la multiplicación si el producto es multi-unidad).
- unidad = "unidad", precio_unitario = precio_unitario_visible / unidades_por_paquete.
- empaque_detalle: explica el desglose (ej: "3 paquetes × 10 u = 30 unidades totales").

FORMATO VANNI (RUT 76.979.850-1): cantidades directas, precios netos, TOTAL incluye IVA.

PRODUCTOS CONOCIDOS CON PAQUETE MULTI-UNIDAD (APLICA LA MULTIPLICACIÓN):
- "BIG MONTINA" / "SALCHICHA POLLO SURENA BIG MONTINA" (cualquier variante) → 10 unidades por paquete.
  Ej: boleta dice "3 X \$4.290" → cantidad=30, unidad="unidad", precio_unitario=429, empaque_detalle="3 paquetes × 10u c/u = 30u total"
- "LAMINADO DE TOCINO" → 1 unidad = 50g. cantidad = número de láminas × 0.05 (kg).
- "PAN DE COMPLETO" / "PAN DE HAMBURGUESA" → bolsa. cantidad=1 por bolsa, unidad="unidad".

{$rutMap}
Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptProducto(array $contexto): string
    {
        $products = $this->formatProducts($contexto);
        $equivalences = '';
        foreach (array_slice($contexto['equivalences'] ?? [], 0, 10) as $eq) {
            $equivalences .= "- {$eq['nombre']} → {$eq['ingrediente']} ({$eq['cantidad_por_unidad']} {$eq['unidad']})\n";
        }

        return <<<PROMPT
Analiza esta imagen de producto (ingrediente físico) y extrae datos estructurados.

REGLAS:
- Identifica qué producto es (tomate, papa, carne, pan, etc.)
- Estima cantidad basándote en el tamaño visual y contexto
- Caja estándar de tomates ≈ 18-20 kg, saco de papas ≈ 25 kg
- Bolsas azules/rosadas con productos redondos oscuros = probablemente Palta Hass
- Si hay texto de peso visible en el empaque (ej: "500g", "1kg", "250ml"), usa ESE peso como cantidad. Ejemplo: empaque de 500g → cantidad=0.5, unidad="kg". Empaque de 1.5L → cantidad=1.5, unidad="lt".
- NO redondees el peso a 1 kg si el empaque dice 500g. Usa exactamente lo que dice el empaque.
- tipo_imagen = "producto"
- fecha: NO uses fechas de vencimiento o fabricación del empaque. Si no hay fecha de compra visible, usa null.

PACKAGING, LIMPIEZA E INSUMOS:
- Para servilletas, papel, bolsas, guantes, detergente, cloro, etc.: tipo_compra = "insumos", NO "ingredientes".
- Los números en el empaque describen la PRESENTACIÓN (ej: "500 hojas", "100 unidades"), NO la cantidad comprada.
- Si el empaque dice "NxPrecio" (ej: "6x$8.500"), significa N unidades incluidas en el paquete por ese precio TOTAL. Entonces: cantidad=N, precio_unitario=Precio/N, subtotal=Precio. Ejemplo: "6x$8.500" → cantidad=6, precio_unitario=1417, subtotal=8500.
- Si NO hay precio visible en la imagen, usa precio_unitario=0 y subtotal=0. NO inventes precios.
- Para alimentos con peso (queso, carne, etc.): tipo_compra = "ingredientes".

SOBRES Y SACHETS PEQUEÑOS (<100g):
- Para sobres de condimentos, salsas en polvo, especias: unidad = "unidad", NO convertir gramos a kg.
- Lee el nombre EXACTO impreso en el empaque (marca + producto). Ej: "Culantrito con Espinaca Sibarita", no "Sal".
- Si hay texto tipo "3x$1.000", son 3 unidades por $1.000 total. Precio unitario = $333.

NOTAS MANUSCRITAS DE ENTREGA:
- Si la imagen es una nota escrita a mano (papel con texto manuscrito), interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.

Equivalencias conocidas:
{$equivalences}

Ingredientes del negocio: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptBascula(array $contexto): string
    {
        $products = $this->formatProducts($contexto);

        return <<<PROMPT
Analiza esta imagen de báscula/balanza digital y extrae datos estructurados.

REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO (\$/kg), TOTAL (\$)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 (\$45 = \$4.500/kg)
- Si número < 1000 en total → multiplicar ×100 (\$237 = \$23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"
- fecha: NO uses fechas de vencimiento o fabricación. Si no hay fecha de compra visible, usa null.

Ingredientes del negocio: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptTransferencia(array $contexto): string
    {
        $personMap = '';
        foreach ($contexto['person_map'] ?? [] as $person => $supplier) {
            $personMap .= "- {$person} → {$supplier}\n";
        }

        return <<<PROMPT
Analiza esta imagen de comprobante de transferencia bancaria y extrae datos estructurados.

REGLAS:
- PROVEEDOR = DESTINATARIO de la transferencia (NO el banco, NO Mercado Pago)
- metodo_pago = "transfer" siempre
- Extrae: destinatario, monto, fecha

Mapeo personas → proveedores:
{$personMap}
- Si el destinatario no está en el mapeo, usar su nombre como proveedor
- Para ARIAKA: item = "Servicios Delivery", tipo_compra = "otros"
- Para Abastible/Elton San Martin: item = "gas 15", tipo_compra = "ingredientes"

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    private function promptGeneral(array $contexto): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        return <<<PROMPT
Analiza esta imagen y determina qué tipo de contenido es. Extrae datos estructurados.

Tipos posibles: boleta, factura, producto, bascula, transferencia.

- Boleta/Factura: proveedor, RUT, items, montos, IVA
- Producto: identificar producto, estimar cantidad
- Báscula: leer displays (peso, precio/kg, total). Notación abreviada: ×100
- Transferencia: destinatario = proveedor (NO el banco)

Montos en pesos chilenos enteros. Si no hay IVA: neto=round(total/1.19).

NOTAS MANUSCRITAS DE ENTREGA:
- Si la imagen es una nota escrita a mano (papel con texto manuscrito), interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.
  - tipo_imagen = "producto", tipo_compra = "ingredientes"

{$rutMap}
Proveedores: {$suppliers}
Ingredientes: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".
Incluye el campo "categoria_sugerida" en cada ítem.

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    // ─── Vision Agent Prompt & Schema (Task 2.2) ───

    private function buildVisionPrompt(): string
    {
        return <<<'PROMPT'
Observa esta imagen con atención y extrae la información esencial.

1. TEXTO CRUDO: Transcribe SOLO el texto relevante para identificar la compra: RUTs, montos, fechas, nombres de productos/servicios/proveedores, números de operación. NO transcribir textos legales, disclaimers,ni líneas de UI repetitivas (botones, menús). Mantén el texto en una sola línea separada por espacios. Si no hay texto relevante, escribe "Sin texto visible".

2. DESCRIPCIÓN VISUAL: Describe brevemente lo que ves (1-2 frases):
   - Objetos principales: cajas, sacos, bolsas, bandejas, balanzas, documentos impresos
   - Productos visibles: tomate, papa, carne, pan, etc.
   - Contexto: mostrador de feria, estante de supermercado, cocina, comprobante digital
   Si es un documento (boleta/factura), describe el formato brevemente.

3. CLASIFICACIÓN: Determina el tipo de imagen:
   - "boleta": documento de venta con RUT, productos, total
   - "factura": documento tributario formal con RUT emisor/receptor, IVA
   - "producto": foto de producto físico (caja, saco, bolsa, bandeja) O nota manuscrita de entrega
   - "bascula": báscula/balanza digital mostrando peso
   - "transferencia": comprobante de transferencia bancaria
   - "desconocido": no se puede determinar

IMPORTANTE: Las notas manuscritas de entrega (papel con texto escrito a mano indicando producto, cantidad, peso y precio) deben clasificarse como "producto", NO como "desconocido".

Responde con texto_crudo, descripcion_visual, tipo_imagen, confianza (0.0-1.0) y razon.
PROMPT;
    }

    private function buildVisionSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'texto_crudo' => ['type' => 'string'],
                'descripcion_visual' => ['type' => 'string'],
                'tipo_imagen' => [
                    'type' => 'string',
                    'enum' => ['boleta', 'factura', 'producto', 'bascula', 'transferencia', 'desconocido'],
                ],
                'confianza' => ['type' => 'number'],
                'razon' => ['type' => 'string'],
            ],
            'required' => ['texto_crudo', 'descripcion_visual', 'tipo_imagen', 'confianza', 'razon'],
        ];
    }

    // ─── Text Analysis Agent Prompt (Task 2.3) ───

    private function buildTextAnalysisPrompt(string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        $fewShotSection = '';
        if (!empty($fewShotExamples)) {
            $lines = [];
            foreach ($fewShotExamples as $fb) {
                $field = $fb['field_name'] ?? '?';
                $orig = is_array($fb['original_value'] ?? null) ? json_encode($fb['original_value']) : ($fb['original_value'] ?? '');
                $corr = is_array($fb['corrected_value'] ?? null) ? json_encode($fb['corrected_value']) : ($fb['corrected_value'] ?? '');
                $lines[] = "- {$field}: '{$orig}' → '{$corr}'";
            }
            $fewShotSection = "\nAPRENDIZAJE DE CORRECCIONES ANTERIORES:\n" . implode("\n", $lines) . "\nAplica estas correcciones si el contexto es similar.\n";
        }

        $typeRules = match ($tipo) {
            'boleta' => $this->textRulesBoleta($contexto),
            'factura' => $this->textRulesFactura($contexto),
            'producto' => $this->textRulesProducto($contexto),
            'bascula' => $this->textRulesBascula($contexto),
            'transferencia' => $this->textRulesTransferencia($contexto),
            default => $this->textRulesGeneral($contexto),
        };

        return <<<PROMPT
Analiza el siguiente texto extraído de una imagen de tipo "{$tipo}" y estructura los datos.

TEXTO EXTRAÍDO DE LA IMAGEN:
{$textoCrudo}

DESCRIPCIÓN VISUAL:
{$descripcionVisual}

{$typeRules}

{$fewShotSection}

{$rutMap}
Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".

Formato respuesta:
{$this->jsonFormat()}
PROMPT;
    }

    /**
     * Prompt combinado: análisis + auto-validación en 1 sola llamada.
     */
    private function buildSelfValidatingAnalysisPrompt(string $textoCrudo, string $descripcionVisual, string $tipo, array $contexto, array $fewShotExamples): string
    {
        $suppliers = $this->formatSuppliers($contexto);
        $products = $this->formatProducts($contexto);
        $rutMap = $this->formatRutMap($contexto);

        $fewShotSection = '';
        if (!empty($fewShotExamples)) {
            $fewShotSection = "\nAPRENDIZAJE DE CORRECCIONES ANTERIORES:\n" . implode("\n", $fewShotExamples) . "\nAplica estas correcciones si el contexto es similar.\n";
        }

        $typeRules = match ($tipo) {
            'boleta' => $this->textRulesBoleta($contexto),
            'factura' => $this->textRulesFactura($contexto),
            'producto' => $this->textRulesProducto($contexto),
            'bascula' => $this->textRulesBascula($contexto),
            'transferencia' => $this->textRulesTransferencia($contexto),
            default => $this->textRulesGeneral($contexto),
        };

        return <<<PROMPT
Analiza y valida el siguiente texto extraído de una imagen de tipo "{$tipo}".

TEXTO EXTRAÍDO DE LA IMAGEN:
{$textoCrudo}

DESCRIPCIÓN VISUAL:
{$descripcionVisual}

{$typeRules}

{$fewShotSection}

{$rutMap}
Proveedores conocidos: {$suppliers}
Ingredientes conocidos: {$products}

CATEGORÍAS DE INGREDIENTES:
Para cada ítem, infiere la categoría más probable entre: Carnes, Vegetales, Salsas, Condimentos, Panes, Embutidos, Pre-elaborados, Lácteos, Bebidas, Gas, Servicios, Packaging, Limpieza.
Si no puedes inferir con confianza, usa "Sin categoría".

AUTO-VALIDACIÓN REQUERIDA (verifica antes de responder):
1. ARITMÉTICA: Para cada item, ¿subtotal ≈ precio_unitario × cantidad? (tolerancia 2%)
2. TOTAL: ¿monto_total ≈ monto_neto + iva + otros_impuestos? (tolerancia 2%)
3. FISCAL: Si hay IVA, ¿iva ≈ monto_neto × 0.19? (tolerancia 2%)
4. PROVEEDOR: ¿El proveedor NO es "La Ruta 11", "Ricardo Huiscaleo" o variantes?
5. FECHA: ¿La fecha es razonable (no es fecha de empaque/vencimiento, no es futura)?
6. ITEMS: ¿Hay al menos 1 item? ¿Los nombres son razonables?

Si encuentras inconsistencias, inclúyelas en el campo "inconsistencias". Cada inconsistencia debe tener: campo, valor_actual, valor_esperado, severidad (error/advertencia), descripcion.
Si todo está correcto, omite "inconsistencias" o déjalo vacío.

Formato respuesta (JSON):
{$this->jsonFormat()}
PROMPT;
    }

    // ─── Text-only type-specific rules (Task 2.3) ───

    private function textRulesBoleta(array $contexto): string
    {
        $patterns = implode("\n", array_slice($contexto['patterns'] ?? [], 0, 10));

        return <<<RULES
REGLAS BOLETA SUPERMERCADO CHILENO:
- Encabezado: RUT, nombre empresa, dirección, sucursal
- Productos: líneas con código de barras + nombre + precio
- FORMATO A (Jumbo/Santa Isabel): cantidad ANTES del producto ("2 X \$4.690")
- FORMATO B (Unimarc/Rendic): cantidad DEBAJO ("2 x 1 UN \$4790 c/u")
- Cada código de barras (78...) = 1 producto separado. NUNCA fusionar.
- Descuentos (OFERTA SEMANA, DESCTO CONVENI) van en campo "descuento" como valor positivo
- Después de "TOTAL" NO hay productos
- "TARJETA DE DEBITO" o "VENTA DEBITO" → metodo_pago = "card"
- Montos en pesos chilenos enteros (sin decimales)
- IMPORTANTE IVA EN BOLETAS CHILENAS: El "Total" de una boleta SIEMPRE es IVA INCLUIDO. Si la boleta dice "El IVA de esta boleta es 3.521", ese IVA ya está DENTRO del total, NO se suma. Entonces: monto_total = Total (tal cual), iva = el valor informado, monto_neto = monto_total - iva. NUNCA hagas monto_total = total + iva.
- Si no hay IVA explícito: monto_neto = round(total/1.19), iva = total - monto_neto

RUTs conocidos:
81.201.000-K = Jumbo/Santa Isabel (Cencosud)
81.537.500-5 = Unimarc (Rendic Hermanos S.A. / SMU)

EQUIVALENCIAS DE PACKAGING (supermercado):
- "PAN DE COMPLETO XL" o "PAN COMPLETO": se vende por bolsa. cantidad=1, unidad="unidad".
- "PAN DE HAMBURGUESA": se vende por bolsa. cantidad=1, unidad="unidad".
- Salsas en botella: cantidad=1, unidad="unidad".
- "BIG MONTINA" o "SALCHICHA POLLO SURENA BIG MONTINA" (cualquier variante): 10 unidades por paquete. DEBES multiplicar: si dice "3 X" → cantidad=30, precio_unitario=precio_total/30, empaque_detalle="3 paquetes × 10u c/u = 30u total".
- "LAMINADO DE TOCINO": 1 unidad = 50g = 0.05kg. Si dice "5 X" → cantidad=0.25, unidad="kg".

REGLA FUNDAMENTAL: Para productos multi-unidad, cantidad DEBE ser el total de unidades individuales. Multiplicá vos mismo, no dejes paquetes sin expandir.

{$patterns}
RULES;
    }

    private function textRulesFactura(array $contexto): string
    {
        return <<<'RULES'
REGLAS FACTURA CHILENA:
- PROVEEDOR = empresa EMISORA (arriba/encabezado), NO el destinatario
- "SEÑORES" o "CLIENTE" = COMPRADOR (La Ruta 11, Ricardo Huiscaleo) — NO es proveedor
- "Yumbel", "Arica" son direcciones del comprador, NO proveedores
- RUT del proveedor está en el encabezado, cerca del nombre de la empresa emisora
- URLs/dominios identifican al proveedor: ariztiaatunegocio.cl → Ariztía, agrosuper.cl → Agrosuper

IMPUESTOS EN FACTURAS CHILENAS — DISTINGUE SEGÚN TIPO:

FACTURA MAYORISTA (Agrosuper, Ariztía, Super Pollo, Vanni, etc.):
- Total = Neto + IVA + otros_impuestos (ICA si aplica).
- IVA (19%): calculado sobre Neto.
- ICA (5%): solo carnicerías, en "otros_impuestos".
- Precios unitarios visibles son NETOS (sin IVA).

FACTURA DE SUPERMERCADO (Cencosud/Jumbo, Santa Isabel, Unimarc, etc.):
- Es como BOLETA pero formato factura (tiene RUT).
- Precios en el detalle INCLUYEN IVA (igual que boleta).
- Neto = Total / 1.19. IVA = Total - Neto.
- Extrae NETO, IVA y TOTAL tal como aparecen.

EMPAQUE EN FACTURAS MAYORISTAS:
- "SALCHICHA BIG MONT 800G 10U 8X1" CANT=2 → 10×8×2 = 160 unidades
- "NNu" = unidades/paquete, "NNxN" = paquetes/caja, CANT = bultos comprados
- nombre: LIMPIO sin empaque. precio_unitario: subtotal/cantidad_total
- empaque_detalle: "10u/paq × 8paq/caja × 2 cajas = 160 unidades"

FACTURAS DE SUPERMERCADO (Cencosud/Jumbo, Santa Isabel, Unimarc, etc.):
- Cuando veas formato CONSUMIDOR tipo "3 X $4.890" (cantidad × precio unitario), NO es formato mayorista.
- cantidad = número de paquetes × unidades_por_paquete (DEBES hacer la multiplicación si el producto es multi-unidad).
- unidad = "unidad", precio_unitario = precio_unitario_visible / unidades_por_paquete.
- empaque_detalle: detalla el desglose (ej: "3 paquetes × 10u c/u = 30u total").

FORMATO VANNI (RUT 76.979.850-1): cantidades directas, precios netos, TOTAL incluye IVA.

PRODUCTOS CONOCIDOS CON PAQUETE MULTI-UNIDAD (APLICA LA MULTIPLICACIÓN):
- "BIG MONTINA" / "SALCHICHA POLLO SURENA BIG MONTINA" (cualquier variante) → 10 unidades por paquete.
  Ej: boleta dice "3 X $4.290" → cantidad=30, unidad="unidad", precio_unitario=429, empaque_detalle="3 paquetes × 10u c/u = 30u total"
- "LAMINADO DE TOCINO" → 1 unidad = 50g = 0.05kg. Si dice "5 X" → cantidad=0.25, unidad="kg".
- "PAN DE COMPLETO" / "PAN DE HAMBURGUESA" → bolsa. cantidad=1, unidad="unidad".
RULES;
    }

    private function textRulesProducto(array $contexto): string
    {
        $equivalences = '';
        foreach (array_slice($contexto['equivalences'] ?? [], 0, 10) as $eq) {
            $equivalences .= "- {$eq['nombre']} → {$eq['ingrediente']} ({$eq['cantidad_por_unidad']} {$eq['unidad']})\n";
        }

        return <<<RULES
REGLAS PRODUCTO FÍSICO:
- Identifica qué producto es (tomate, papa, carne, pan, etc.)
- Estima cantidad basándote en el tamaño visual y contexto
- Caja estándar de tomates ≈ 18-20 kg, saco de papas ≈ 25 kg
- Bolsas azules/rosadas con productos redondos oscuros = probablemente Palta Hass
- Si hay texto de peso visible, úsalo. Si no, estima.
- tipo_imagen = "producto"
- fecha: NO uses fechas de vencimiento o fabricación del empaque. Si no hay fecha de compra visible, usa null.

PACKAGING, LIMPIEZA E INSUMOS:
- Para servilletas, papel, bolsas, guantes, detergente, cloro, etc.: tipo_compra = "insumos", NO "ingredientes".
- Los números en el empaque describen la PRESENTACIÓN (ej: "500 hojas", "100 unidades"), NO la cantidad comprada.
- Si el empaque dice "NxPrecio" (ej: "6x$8.500"), significa N unidades por ese precio TOTAL. cantidad=N, precio_unitario=Precio/N, subtotal=Precio.
- Si NO hay precio visible, usa precio_unitario=0 y subtotal=0. NO inventes precios.
- Para alimentos con peso (queso, carne, etc.): tipo_compra = "ingredientes".

SOBRES Y SACHETS PEQUEÑOS (<100g):
- Para sobres de condimentos, salsas en polvo, especias: unidad = "unidad", NO convertir gramos a kg.
- Lee el nombre EXACTO impreso en el empaque (marca + producto).
- Si hay texto tipo "3x$1.000", son 3 unidades por $1.000 total. Precio unitario = $333.

NOTAS MANUSCRITAS DE ENTREGA:
- Si el texto proviene de una nota escrita a mano, interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.

Equivalencias conocidas:
{$equivalences}
RULES;
    }

    private function textRulesBascula(array $contexto): string
    {
        return <<<'RULES'
REGLAS BÁSCULAS DE FERIA CHILENA:
- 3 displays: PESO (kg), PRECIO ($/kg), TOTAL ($)
- NOTACIÓN ABREVIADA: si número < 200 en precio → multiplicar ×100 ($45 = $4.500/kg)
- Si número < 1000 en total → multiplicar ×100 ($237 = $23.700)
- peso_bascula: número EXACTO del display en kg (ej: 5.275)
- Marcas: FERRAWYY, HENKEL, CAMRY, EXCELL, T-SCALE
- Si se ve el producto (paltas, tomates), identificarlo
- tipo_imagen = "bascula"
- fecha: NO uses fechas de vencimiento o fabricación. Si no hay fecha de compra visible, usa null.
RULES;
    }

    private function textRulesTransferencia(array $contexto): string
    {
        $personMap = '';
        foreach ($contexto['person_map'] ?? [] as $person => $supplier) {
            $personMap .= "- {$person} → {$supplier}\n";
        }

        return <<<RULES
REGLAS TRANSFERENCIA BANCARIA:
- PROVEEDOR = DESTINATARIO de la transferencia (NO el banco, NO Mercado Pago)
- metodo_pago = "transfer" siempre
- Extrae: destinatario, monto, fecha

Mapeo personas → proveedores:
{$personMap}
- Si el destinatario no está en el mapeo, usar su nombre como proveedor
- Para ARIAKA: item = "Servicios Delivery", tipo_compra = "otros"
- Para Abastible/Elton San Martin: item = "gas 15", tipo_compra = "ingredientes"
RULES;
    }

    private function textRulesGeneral(array $contexto): string
    {
        return <<<'RULES'
REGLAS GENERALES:
- Determina qué tipo de contenido es basándote en el texto y descripción visual
- Boleta/Factura: proveedor, RUT, items, montos, IVA
- Producto: identificar producto, estimar cantidad
- Báscula: leer displays (peso, precio/kg, total). Notación abreviada: ×100
- Transferencia: destinatario = proveedor (NO el banco)
- Montos en pesos chilenos enteros. Si no hay IVA: neto=round(total/1.19).

NOTAS MANUSCRITAS DE ENTREGA:
- Si el texto proviene de una nota escrita a mano, interpreta así:
  - Un número seguido de un punto antes del nombre del producto (ej: "7. Pan de Churrasco") indica la CANTIDAD de unidades, NO un número de ítem.
  - "Ruta 11", "Ruta II", "R11" = es el DESTINATARIO (nuestro negocio), NO el proveedor. Deja proveedor vacío/null.
  - "Kg X.XXX" = peso total del pedido (informativo), pero la unidad de compra es por UNIDAD, no por kilo.
  - El precio mostrado (ej: "$3.400") es el TOTAL. Calcula precio_unitario = total / cantidad.
  - Para panes: siempre se compran por unidad. Si dice "7. Pan..." y "$3.400", son 7 unidades a $486 c/u.
  - tipo_imagen = "producto", tipo_compra = "ingredientes"
RULES;
    }

    // ─── Validation Agent Prompt & Schema (Task 2.4) ───

    private function buildValidationPrompt(array $datos, array $contexto): string
    {
        $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $proveedores = implode(', ', array_slice($contexto['suppliers'] ?? [], 0, 20));

        return <<<PROMPT
Valida la coherencia de estos datos extraídos de una compra chilena.

DATOS EXTRAÍDOS:
{$datosJson}

PROVEEDORES CONOCIDOS: {$proveedores}

VERIFICACIONES REQUERIDAS:
1. ARITMÉTICA: Para cada item, ¿subtotal ≈ precio_unitario × cantidad? (tolerancia 2%)
2. TOTAL: ¿monto_total ≈ monto_neto + iva + otros_impuestos? (tolerancia 2%). Si no hay otros_impuestos, ¿monto_total ≈ monto_neto + iva?
3. FISCAL: Si hay IVA, ¿iva ≈ monto_neto × 0.19? (tolerancia 2%). Si hay otros_impuestos, ¿otros_impuestos ≈ monto_neto × 0.05? (tolerancia 5% — ICA varía por tipo de carne)
4. PROVEEDOR: ¿El proveedor NO es "La Ruta 11", "Ricardo Huiscaleo" o variantes? (esos son el COMPRADOR, no proveedor)
5. FECHA: ¿La fecha es razonable (no es fecha de empaque/vencimiento, no es futura)?
6. ITEMS: ¿Hay al menos 1 item? ¿Los nombres son razonables?

Para cada inconsistencia encontrada, indica: campo afectado, valor actual, valor esperado, severidad (error/advertencia), y descripción.
Si todo está correcto, retorna inconsistencias vacías y datos_validados = datos originales.
PROMPT;
    }

    private function buildValidationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'datos_validados' => ['type' => 'object'],
                'inconsistencias' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'campo' => ['type' => 'string'],
                            'valor_actual' => ['type' => 'string'],
                            'valor_esperado' => ['type' => 'string'],
                            'severidad' => ['type' => 'string', 'enum' => ['error', 'advertencia']],
                            'descripcion' => ['type' => 'string'],
                        ],
                        'required' => ['campo', 'valor_actual', 'valor_esperado', 'severidad', 'descripcion'],
                    ],
                ],
            ],
            'required' => ['datos_validados', 'inconsistencias'],
        ];
    }

    // ─── Reconciliation Agent Prompt & Schema (Task 2.5) ───

    private function buildReconciliationPrompt(array $datos, array $inconsistencias, string $textoCrudo, array $contexto): string
    {
        $datosJson = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $inconsJson = json_encode($inconsistencias, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $proveedores = implode(', ', array_slice($contexto['suppliers'] ?? [], 0, 20));

        return <<<PROMPT
Se encontraron inconsistencias en datos extraídos de una compra. Intenta resolverlas.

DATOS ACTUALES:
{$datosJson}

INCONSISTENCIAS DETECTADAS:
{$inconsJson}

TEXTO ORIGINAL DE LA IMAGEN:
{$textoCrudo}

PROVEEDORES CONOCIDOS: {$proveedores}

INSTRUCCIONES:
1. Para cada inconsistencia, intenta resolverla usando el texto original y el contexto.
2. Si puedes resolver con confianza, aplica la corrección en datos_finales y describe qué hiciste en correcciones_aplicadas.
3. Si NO puedes resolver con confianza, genera una pregunta para el usuario con opciones claras.
4. Las preguntas deben tener al menos 2 opciones con valor y etiqueta descriptiva.

Retorna datos_finales (con correcciones aplicadas), correcciones_aplicadas (lista de strings), y preguntas (para el usuario).
PROMPT;
    }

    private function buildReconciliationSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'datos_finales' => ['type' => 'object'],
                'correcciones_aplicadas' => ['type' => 'array', 'items' => ['type' => 'string']],
                'preguntas' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'campo' => ['type' => 'string'],
                            'descripcion' => ['type' => 'string'],
                            'opciones' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'valor' => ['type' => 'string'],
                                        'etiqueta' => ['type' => 'string'],
                                    ],
                                    'required' => ['valor', 'etiqueta'],
                                ],
                            ],
                        ],
                        'required' => ['campo', 'descripcion', 'opciones'],
                    ],
                ],
            ],
            'required' => ['datos_finales', 'correcciones_aplicadas', 'preguntas'],
        ];
    }
}
