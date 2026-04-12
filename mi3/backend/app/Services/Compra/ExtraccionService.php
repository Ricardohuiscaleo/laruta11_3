<?php

namespace App\Services\Compra;

use App\Models\AiExtractionLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExtraccionService
{
    private string $modelId = 'amazon.nova-pro-v1:0';
    private int $timeoutSeconds = 20;

    /**
     * Extract structured data from a boleta/factura image using Amazon Bedrock (Nova Lite).
     *
     * @param string $imageUrl S3 URL or temp key of the image
     * @param array|null $context Optional context from learned patterns (supplier history, product patterns)
     * @return array ExtractionResult with confidence scores
     */
    public function extractFromImage(string $imageUrl, ?array $context = null): array
    {
        $startTime = microtime(true);

        try {
            // Get image contents as base64
            $imageBase64 = $this->getImageBase64($imageUrl);

            if (!$imageBase64) {
                return $this->failedResult($imageUrl, 'No se pudo obtener la imagen', $startTime);
            }

            // Build context from learned patterns if not provided
            if ($context === null) {
                $context = $this->buildLearnedContext();
            }

            // Call Bedrock with context-aware prompt
            $rawResponse = $this->callBedrock($imageBase64, $context);

            if (!$rawResponse) {
                return $this->failedResult($imageUrl, 'Bedrock no retornó respuesta', $startTime);
            }

            // Parse the JSON from the response
            $extracted = $this->parseResponse($rawResponse);

            if (!$extracted) {
                $this->saveLog($imageUrl, $rawResponse, [], [], 0, $startTime, 'failed', 'No se pudo interpretar la boleta');
                return [
                    'success' => false,
                    'error' => 'No se pudo interpretar la boleta',
                    'fallback' => 'manual',
                ];
            }

            // Calculate confidence scores
            $confidenceScores = $this->calculateConfidence($extracted);
            $overallConfidence = $this->calculateOverallConfidence($confidenceScores);

            // Ensure amounts are integers (Chilean pesos)
            $extracted = $this->normalizeAmounts($extracted);

            // Save log
            $log = $this->saveLog(
                $imageUrl,
                $rawResponse,
                $extracted,
                $confidenceScores,
                $overallConfidence,
                $startTime,
                'success'
            );

            return [
                'success' => true,
                'extraction_log_id' => $log->id,
                'data' => $extracted,
                'confianza' => $confidenceScores,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $log->processing_time_ms,
            ];
        } catch (\Exception $e) {
            Log::error('[ExtraccionService] Error: ' . $e->getMessage());
            return $this->failedResult($imageUrl, $e->getMessage(), $startTime);
        }
    }

    /**
     * Get image as base64 string from S3 URL or key.
     */
    private function getImageBase64(string $imageUrl): ?string
    {
        try {
            // If it's an S3 key (not a full URL), read from S3
            if (!str_starts_with($imageUrl, 'http')) {
                $contents = Storage::disk('s3')->get($imageUrl);
                return $contents ? base64_encode($contents) : null;
            }

            // Download from URL
            $response = Http::timeout(5)->get($imageUrl);
            if ($response->successful()) {
                return base64_encode($response->body());
            }

            return null;
        } catch (\Exception $e) {
            Log::warning('[ExtraccionService] Failed to get image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Call Amazon Bedrock Nova Lite with the image.
     * Uses a multi-type prompt that handles: boletas/facturas, product photos, scale photos.
     */
    private function callBedrock(string $imageBase64, ?array $context = null): ?array
    {
        $region = config('services.aws.region', env('AWS_DEFAULT_REGION', 'us-east-1'));
        $accessKey = config('services.aws.key', env('AWS_ACCESS_KEY_ID'));
        $secretKey = config('services.aws.secret', env('AWS_SECRET_ACCESS_KEY'));

        if (!$accessKey || !$secretKey) {
            throw new \RuntimeException('AWS credentials not configured');
        }

        $prompt = $this->buildPrompt($context);

        $body = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'image' => [
                                'format' => 'jpeg',
                                'source' => [
                                    'bytes' => $imageBase64,
                                ],
                            ],
                        ],
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'inferenceConfig' => [
                'maxNewTokens' => 2048,
                'temperature' => 0.1,
            ],
        ];

        // Use AWS SDK if available, otherwise use HTTP with SigV4
        $endpoint = "https://bedrock-runtime.{$region}.amazonaws.com/model/{$this->modelId}/converse";

        $response = $this->signedRequest($endpoint, $body, $region, $accessKey, $secretKey);

        return $response;
    }

    /**
     * Build a context-aware prompt that handles multiple image types.
     * 
     * Types detected by the AI:
     * 1. Boleta/Factura → extract proveedor, RUT, items, montos, IVA
     * 2. Foto de producto (caja de tomates, bolsa de pan, etc.) → identify product, estimate quantity
     * 3. Foto de báscula/balanza → read weight from digital display
     * 4. Factura de proveedor conocido (Shipo, etc.) → use known format patterns
     */
    private function buildPrompt(?array $context = null): string
    {
        $knownSuppliers = '';
        $knownProducts = '';
        $productPatterns = '';
        $rutMapping = '';

        if ($context) {
            if (!empty($context['suppliers'])) {
                $supplierList = implode(', ', array_slice($context['suppliers'], 0, 20));
                $knownSuppliers = "\n\nProveedores conocidos del negocio: {$supplierList}";
            }
            if (!empty($context['rut_map'])) {
                $rutLines = [];
                foreach ($context['rut_map'] as $rut => $nombre) {
                    $rutLines[] = "RUT {$rut} = {$nombre}";
                }
                $rutMapping = "\n\nMapeo RUT → Proveedor (si ves alguno de estos RUTs en la boleta, usa el nombre del proveedor):\n" . implode("\n", $rutLines);
            }
            if (!empty($context['products'])) {
                $productList = implode(', ', array_slice($context['products'], 0, 30));
                $knownProducts = "\n\nIngredientes/productos conocidos: {$productList}";
            }
            if (!empty($context['patterns'])) {
                $patternList = implode("\n", array_map(fn($p) => "- {$p}", $context['patterns']));
                $productPatterns = "\n\nPatrones aprendidos de compras anteriores:\n{$patternList}";
            }
        }

        return <<<PROMPT
Analiza esta imagen y determina qué tipo de contenido es. Responde SOLO con JSON.

TIPO 1 — BOLETA O FACTURA (documento impreso con texto, montos, RUT):
Extrae: proveedor, rut_proveedor, items (array con nombre/cantidad/unidad/precio_unitario/subtotal), monto_neto, iva, monto_total.

TIPO 2 — FOTO DE PRODUCTO (caja, bolsa, envase de ingrediente):
Identifica el producto y estima la cantidad. Ejemplos:
- Caja de tomates → "Tomate", cantidad estimada según tamaño de caja (caja estándar = 3 kg)
- Bolsa de pan → "Pan", contar unidades visibles
- Bandeja de carne → "Carne", estimar peso por tamaño
Extrae: items (array con nombre/cantidad_estimada/unidad/confianza_estimacion).

TIPO 3 — FOTO DE BÁSCULA/BALANZA (display digital mostrando peso):
Lee el número del display digital. Extrae: peso_leido (número), unidad (kg/g), producto_probable (si se ve el producto en la báscula).

TIPO 4 — COMPROBANTE DE TRANSFERENCIA BANCARIA (Mercado Pago, banco, etc.):
Extrae: destinatario (nombre de la persona que recibe), monto, fecha de la transferencia.
IMPORTANTE: El proveedor NO es "Mercado Pago" ni el banco. El proveedor es según el DESTINATARIO.
Mapeo conocido de personas a proveedores:
- Karen Miranda Olmedo = ARIAKA (Servicios Delivery)
- Eliana Vilca = ARIAKA (Servicios Delivery)
- Cecilia Rojas Hinojosa = ARIAKA (Servicios Delivery)
- Maria Mondañez Mamani = ARIAKA (Servicios Delivery)
- Maria Mondanez Mamani = ARIAKA (Servicios Delivery)
- Giovanna Loza Salas = ARIAKA (Servicios Delivery)
- Ariel Araya = ARIAKA (Servicios Delivery)
- Ariel Aliro Araya Villalobos = ARIAKA (Servicios Delivery)
- Karina Andrea Muñoz Ahumada = Ariztía (proveedor)
- Lucila Cacera = agro-lucila
- Si el destinatario no está en el mapeo, usar el nombre de la persona como proveedor.
Para ARIAKA: item = "Servicios Delivery", cantidad = 1, unidad = "unidad", tipo_compra = "otros".

TIPO 5 — FACTURA DE PROVEEDOR CONOCIDO:
Si reconoces el formato de un proveedor específico (ej: Shipo, DistribuChile, etc.), usa el formato conocido para extraer datos con mayor precisión.
Proveedores que SIEMPRE se pagan con transferencia (metodo_pago = "transfer"):
- Ariztía, Ariztía (proveedor), Karina Andrea Muñoz Ahumada → metodo_pago = "transfer"
- agrosuper, agrosuper (proveedor) → metodo_pago = "transfer"
- ideal → metodo_pago = "transfer"
- agro-lucila, Lucila Cacera → metodo_pago = "transfer"
- ARIAKA → metodo_pago = "transfer"
- JumboAPP → metodo_pago = "transfer"
{$knownSuppliers}{$rutMapping}{$knownProducts}{$productPatterns}

Formato de respuesta JSON:
{
  "tipo_imagen": "boleta" | "factura" | "producto" | "bascula" | "transferencia" | "desconocido",
  "proveedor": "nombre del proveedor (NO el banco ni Mercado Pago)",
  "rut_proveedor": "XX.XXX.XXX-Y o null",
  "fecha": "YYYY-MM-DD (fecha de la compra/transferencia)",
  "metodo_pago": "cash" | "transfer" | "card" | "credit",
  "tipo_compra": "ingredientes" | "insumos" | "equipamiento" | "otros",
  "items": [{"nombre": "...", "cantidad": N, "unidad": "kg|unidad|g|L", "precio_unitario": N, "subtotal": N}],
  "monto_neto": N o null,
  "iva": N o null,
  "monto_total": N o null,
  "peso_bascula": N o null,
  "unidad_bascula": "kg" o null,
  "notas_ia": "observaciones sobre lo que se ve en la imagen"
}

Reglas:
- Montos en pesos chilenos como números enteros (sin decimales, sin puntos de miles)
- Si no puedes leer un campo, usa null
- Si no hay desglose de IVA, calcula: monto_neto = round(monto_total / 1.19), iva = monto_total - monto_neto
- Para fotos de productos, estima cantidad basándote en el tamaño visual y patrones conocidos
- Para básculas, lee el número exacto del display digital
- SIEMPRE extrae la fecha si es visible en la imagen (formato YYYY-MM-DD)
- Para transferencias: proveedor = destinatario (NO el banco/Mercado Pago), metodo_pago = "transfer"
- Para comprobantes de pago a personas conocidas (delivery, servicios), tipo_compra = "otros"
- Responde SOLO con el JSON, sin texto adicional
PROMPT;
    }

    /**
     * Build learned context from historical data.
     * Queries supplier_index and ingredients to provide context to the AI.
     */
    private function buildLearnedContext(): array
    {
        $context = [
            'suppliers' => [],
            'products' => [],
            'patterns' => [],
        ];

        try {
            // Known suppliers from supplier_index (with RUTs)
            $supplierRecords = \App\Models\SupplierIndex::orderBy('frecuencia', 'desc')
                ->limit(20)
                ->get(['nombre_original', 'rut']);
            $context['suppliers'] = $supplierRecords->pluck('nombre_original')->toArray();
            
            // RUT → proveedor mapping for identification
            $rutMap = [];
            foreach ($supplierRecords as $s) {
                if ($s->rut) {
                    $rutMap[$s->rut] = $s->nombre_original;
                }
            }
            $context['rut_map'] = $rutMap;

            // Known ingredients
            $ingredients = \App\Models\Ingredient::where('is_active', 1)
                ->orderBy('name')
                ->pluck('name')
                ->toArray();
            $context['products'] = $ingredients;

            // Learned patterns from training dataset and feedback
            $patterns = $this->getLearnedPatterns();
            $context['patterns'] = $patterns;
        } catch (\Exception $e) {
            Log::warning('[ExtraccionService] Failed to build context: ' . $e->getMessage());
        }

        return $context;
    }

    /**
     * Get learned patterns from historical extractions and user feedback.
     * These patterns help the AI make better predictions.
     */
    private function getLearnedPatterns(): array
    {
        $patterns = [];

        try {
            // Pattern 1: Product-to-quantity mappings from confirmed purchases
            // e.g., "Caja de tomates" → always ~3 kg
            $productQuantities = \Illuminate\Support\Facades\DB::select("
                SELECT 
                    cd.nombre_item,
                    cd.unidad,
                    ROUND(AVG(cd.cantidad), 1) as cantidad_promedio,
                    COUNT(*) as veces_comprado,
                    c.proveedor
                FROM compras_detalle cd
                JOIN compras c ON cd.compra_id = c.id
                WHERE cd.cantidad > 0
                GROUP BY cd.nombre_item, cd.unidad, c.proveedor
                HAVING veces_comprado >= 2
                ORDER BY veces_comprado DESC
                LIMIT 15
            ");

            foreach ($productQuantities as $pq) {
                $patterns[] = "{$pq->nombre_item} de {$pq->proveedor}: generalmente {$pq->cantidad_promedio} {$pq->unidad} (comprado {$pq->veces_comprado} veces)";
            }

            // Pattern 2: Supplier-specific item patterns
            // e.g., "Shipo siempre vende: pan, queso, jamón"
            $supplierItems = \App\Models\SupplierIndex::whereNotNull('items_habituales')
                ->orderBy('frecuencia', 'desc')
                ->limit(10)
                ->get();

            foreach ($supplierItems as $si) {
                $habituales = $si->items_habituales ?? [];
                if (count($habituales) > 0) {
                    $itemNames = array_map(fn($h) => $h['nombre'] ?? '', array_slice($habituales, 0, 5));
                    $itemNames = array_filter($itemNames);
                    if (!empty($itemNames)) {
                        $patterns[] = "Proveedor '{$si->nombre_original}' vende habitualmente: " . implode(', ', $itemNames);
                    }
                }
            }

            // Pattern 3: Corrections from user feedback (what the AI got wrong)
            $corrections = \Illuminate\Support\Facades\DB::select("
                SELECT field_name, original_value, corrected_value, COUNT(*) as times
                FROM extraction_feedback
                GROUP BY field_name, original_value, corrected_value
                HAVING times >= 2
                ORDER BY times DESC
                LIMIT 10
            ");

            foreach ($corrections as $c) {
                $patterns[] = "Corrección frecuente: cuando la IA lee '{$c->original_value}' en {$c->field_name}, el valor correcto es '{$c->corrected_value}'";
            }
        } catch (\Exception $e) {
            Log::warning('[ExtraccionService] Failed to get learned patterns: ' . $e->getMessage());
        }

        return $patterns;
    }

    /**
     * Make a signed AWS request using SigV4.
     */
    private function signedRequest(string $url, array $body, string $region, string $accessKey, string $secretKey): ?array
    {
        $service = 'bedrock';
        $host = parse_url($url, PHP_URL_HOST);
        $rawPath = parse_url($url, PHP_URL_PATH);
        // SigV4 requires URI-encoded path segments (: → %3A) for canonical request
        $pathSegments = explode('/', $rawPath);
        $encodedPath = implode('/', array_map(fn($s) => rawurlencode($s), $pathSegments));
        $jsonBody = json_encode($body);

        $now = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $headers = [
            'content-type' => 'application/json',
            'host' => $host,
            'x-amz-date' => $now,
        ];

        // Canonical request
        $signedHeaders = implode(';', array_keys($headers));
        $canonicalHeaders = '';
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= "{$k}:{$v}\n";
        }
        $payloadHash = hash('sha256', $jsonBody);

        $canonicalRequest = implode("\n", [
            'POST',
            $encodedPath,
            '', // query string
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        // String to sign
        $credentialScope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $now,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $kDate = hash_hmac('sha256', $date, "AWS4{$secretKey}", true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        try {
            // Use curl directly to avoid Guzzle/Laravel HTTP double-encoding the URL
            // (the ':' in model ID gets encoded to %3A, then Guzzle re-encodes to %253A)
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $jsonBody,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'X-Amz-Date: ' . $now,
                    'Authorization: ' . $authorization,
                ],
            ]);
            $responseBody = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                if (str_contains($curlError, 'timed out')) {
                    throw new \RuntimeException('Extracción agotó tiempo de espera');
                }
                Log::error("[ExtraccionService] Curl error: {$curlError}");
                return null;
            }

            if ($httpCode === 200) {
                return json_decode($responseBody, true);
            }

            Log::error("[ExtraccionService] Bedrock error: HTTP {$httpCode} " . substr($responseBody, 0, 500));
            return null;
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('[ExtraccionService] Request error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Parse the Bedrock Converse API response to extract JSON data.
     */
    private function parseResponse(?array $rawResponse): ?array
    {
        if (!$rawResponse) {
            return null;
        }

        // Nova Lite Converse API response structure
        $text = $rawResponse['output']['message']['content'][0]['text'] ?? null;

        if (!$text) {
            return null;
        }

        // Try to extract JSON from the response text
        $text = trim($text);

        // Remove markdown code blocks if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('[ExtraccionService] Failed to parse JSON: ' . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    /**
     * Calculate confidence scores per field based on presence, validity, and image type.
     */
    private function calculateConfidence(array $extracted): array
    {
        $scores = [
            'proveedor' => 0.0,
            'rut' => 0.0,
            'items' => 0.0,
            'monto_neto' => 0.0,
            'iva' => 0.0,
            'monto_total' => 0.0,
            'tipo_imagen' => 0.0,
            'peso_bascula' => 0.0,
        ];

        $tipoImagen = $extracted['tipo_imagen'] ?? 'desconocido';
        $scores['tipo_imagen'] = in_array($tipoImagen, ['boleta', 'factura', 'producto', 'bascula']) ? 0.9 : 0.3;

        // Proveedor: present and non-empty
        if (!empty($extracted['proveedor']) && is_string($extracted['proveedor'])) {
            $scores['proveedor'] = strlen($extracted['proveedor']) > 2 ? 0.9 : 0.5;
        }

        // RUT: valid Chilean format
        if (!empty($extracted['rut_proveedor'])) {
            $scores['rut'] = preg_match('/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/', $extracted['rut_proveedor']) ? 0.95 : 0.4;
        }

        // Items: array with valid structure
        if (!empty($extracted['items']) && is_array($extracted['items'])) {
            $validItems = 0;
            foreach ($extracted['items'] as $item) {
                if (!empty($item['nombre']) && isset($item['cantidad'])) {
                    // For product photos, precio_unitario may be null (just identifying the product)
                    if ($tipoImagen === 'producto' || $tipoImagen === 'bascula') {
                        $validItems++;
                    } elseif (isset($item['precio_unitario'])) {
                        $validItems++;
                    }
                }
            }
            $total = count($extracted['items']);
            $scores['items'] = $total > 0 ? round($validItems / $total, 2) : 0.0;
        }

        // Peso báscula: if image is a scale photo
        if ($tipoImagen === 'bascula' && isset($extracted['peso_bascula']) && is_numeric($extracted['peso_bascula'])) {
            $scores['peso_bascula'] = $extracted['peso_bascula'] > 0 ? 0.85 : 0.3;
        }

        // Monto neto (only relevant for boleta/factura)
        if (in_array($tipoImagen, ['boleta', 'factura'])) {
            if (isset($extracted['monto_neto']) && is_numeric($extracted['monto_neto']) && $extracted['monto_neto'] > 0) {
                $scores['monto_neto'] = 0.9;
            }

            // IVA
            if (isset($extracted['iva']) && is_numeric($extracted['iva']) && $extracted['iva'] > 0) {
                if (isset($extracted['monto_neto']) && $extracted['monto_neto'] > 0) {
                    $expectedIva = round($extracted['monto_neto'] * 0.19);
                    $diff = abs($extracted['iva'] - $expectedIva);
                    $scores['iva'] = $diff <= max(1, $expectedIva * 0.02) ? 0.95 : 0.6;
                } else {
                    $scores['iva'] = 0.7;
                }
            }

            // Monto total
            if (isset($extracted['monto_total']) && is_numeric($extracted['monto_total']) && $extracted['monto_total'] > 0) {
                if (isset($extracted['monto_neto']) && isset($extracted['iva'])) {
                    $expectedTotal = $extracted['monto_neto'] + $extracted['iva'];
                    $diff = abs($extracted['monto_total'] - $expectedTotal);
                    $scores['monto_total'] = $diff <= max(1, $expectedTotal * 0.01) ? 0.95 : 0.6;
                } else {
                    $scores['monto_total'] = 0.8;
                }
            }
        }

        return $scores;
    }

    /**
     * Calculate overall confidence as weighted average.
     */
    private function calculateOverallConfidence(array $scores): float
    {
        $weights = [
            'proveedor' => 0.15,
            'rut' => 0.05,
            'items' => 0.35,
            'monto_neto' => 0.15,
            'iva' => 0.10,
            'monto_total' => 0.20,
        ];

        $weighted = 0;
        $totalWeight = 0;

        foreach ($weights as $field => $weight) {
            if (isset($scores[$field])) {
                $weighted += $scores[$field] * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? round($weighted / $totalWeight, 2) : 0.0;
    }

    /**
     * Normalize monetary amounts to integers (Chilean pesos).
     */
    private function normalizeAmounts(array $extracted): array
    {
        $moneyFields = ['monto_neto', 'iva', 'monto_total'];
        foreach ($moneyFields as $field) {
            if (isset($extracted[$field]) && is_numeric($extracted[$field])) {
                $extracted[$field] = (int) round((float) $extracted[$field]);
            }
        }

        if (!empty($extracted['items']) && is_array($extracted['items'])) {
            foreach ($extracted['items'] as &$item) {
                if (isset($item['precio_unitario']) && is_numeric($item['precio_unitario'])) {
                    $item['precio_unitario'] = (int) round((float) $item['precio_unitario']);
                }
                if (isset($item['subtotal']) && is_numeric($item['subtotal'])) {
                    $item['subtotal'] = (int) round((float) $item['subtotal']);
                }
            }
        }

        return $extracted;
    }

    /**
     * Save extraction log to ai_extraction_logs.
     */
    private function saveLog(
        string $imageUrl,
        ?array $rawResponse,
        array $extractedData,
        array $confidenceScores,
        float $overallConfidence,
        float $startTime,
        string $status,
        ?string $errorMessage = null
    ): AiExtractionLog {
        $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        return AiExtractionLog::create([
            'image_url' => $imageUrl,
            'raw_response' => $rawResponse ?? [],
            'extracted_data' => $extractedData,
            'confidence_scores' => $confidenceScores,
            'overall_confidence' => $overallConfidence,
            'processing_time_ms' => $processingTimeMs,
            'model_id' => $this->modelId,
            'status' => $status,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Return a failed extraction result and save log.
     */
    private function failedResult(string $imageUrl, string $error, float $startTime): array
    {
        $this->saveLog($imageUrl, null, [], [], 0, $startTime, 'failed', $error);

        return [
            'success' => false,
            'error' => $error,
            'fallback' => 'manual',
        ];
    }
}
