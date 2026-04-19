<?php

declare(strict_types=1);

namespace App\Services\Compra;

use App\Models\AiExtractionLog;
use App\Models\Ingredient;
use App\Models\ProductEquivalence;
use App\Models\SupplierIndex;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PipelineExtraccionService
{
    public function __construct(
        private RekognitionService $rekognition,
        private ClasificadorService $clasificador,
        private AnalisisService $analisis,
        private SugerenciaService $sugerencias,
        private GeminiService $gemini,
    ) {}

    /**
     * Execute the multi-model extraction pipeline.
     *
     * @param string        $imageUrl S3 key or URL
     * @param callable|null $onEvent  SSE callback: fn(string $fase, string $status, ?array $data, float $startTime)
     * @return array Complete extraction result (same format as legacy ExtraccionService)
     */
    public function ejecutar(string $imageUrl, ?callable $onEvent = null): array
    {
        $startTime = microtime(true);
        $emit = function (string $fase, string $status, ?array $data) use ($onEvent, $startTime): void {
            if ($onEvent) {
                $onEvent($fase, $status, $data, $startTime);
            }
        };

        // ── Provider detection: Gemini first, then AWS, else error ──
        if ($this->isGeminiAvailable()) {
            return $this->ejecutarGemini($imageUrl, $onEvent);
        }

        // Check if AWS/Bedrock is available as fallback
        if (empty(env('AWS_ACCESS_KEY_ID'))) {
            $emit('error', 'error', ['message' => 'No hay proveedor IA disponible (GOOGLE_API_KEY no configurada, Bedrock bloqueado)']);
            return [
                'success' => false,
                'error' => 'No hay proveedor IA disponible (GOOGLE_API_KEY no configurada, Bedrock bloqueado)',
                'fallback' => 'manual',
            ];
        }

        $pipelinePhases = [];

        try {
            // Resolve S3 key for Rekognition (needs bucket-relative key, not URL)
            $s3Key = $this->resolveS3Key($imageUrl);
            $imageBase64 = $this->getImageBase64($imageUrl);

            if (!$imageBase64) {
                $emit('error', 'error', ['message' => 'No se pudo obtener la imagen']);
                return $this->failedResult($imageUrl, 'No se pudo obtener la imagen', $startTime);
            }

            // ── FASE 1: Percepción (Rekognition paralelo) ──
            $emit('percepcion', 'running', null);
            $phaseStart = microtime(true);

            $perception = ['labels' => [], 'texts' => [], 'elapsed_ms' => 0];
            $perceptionError = null;
            try {
                if ($s3Key) {
                    $perception = $this->rekognition->perceive($s3Key);
                } else {
                    $perceptionError = 'No S3 key resolved from image URL: ' . $imageUrl;
                }
            } catch (\Exception $e) {
                $perceptionError = $e->getMessage();
                Log::warning('[Pipeline] Rekognition failed, continuing: ' . $e->getMessage());
            }

            $pipelinePhases['percepcion'] = [
                'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                'labels_count' => count($perception['labels']),
                'texts_count' => count($perception['texts']),
                'error' => $perceptionError,
                's3_key' => $s3Key,
            ];

            $emit('percepcion', 'done', [
                'labels' => array_map(fn(array $l): string => $l['name'], $perception['labels']),
                'texts_preview' => array_slice(
                    array_map(fn(array $t): string => $t['text'], $perception['texts']),
                    0,
                    8,
                ),
                'elapsed_ms' => $pipelinePhases['percepcion']['elapsed_ms'],
            ]);

            // ── FASE 2: Clasificación (Nova Micro + contexto BD) ──
            $emit('clasificacion', 'running', null);
            $phaseStart = microtime(true);

            $classification = $this->clasificador->clasificar(
                $perception['labels'],
                $perception['texts'],
            );
            $tipo = $classification['tipo_imagen'];
            $contexto = $this->clasificador->cargarContexto($tipo);

            $pipelinePhases['clasificacion'] = [
                'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                'tipo' => $tipo,
                'confianza' => $classification['confianza'],
                'razon' => $classification['razon'] ?? null,
                'api_error' => $classification['api_error'] ?? null,
                'contexto_suppliers' => count($contexto['suppliers'] ?? []),
                'contexto_products' => count($contexto['products'] ?? []),
            ];

            $emit('clasificacion', 'done', [
                'tipo_imagen' => $tipo,
                'confianza' => $classification['confianza'],
                'razon' => $classification['razon'],
                'contexto_proveedores' => count($contexto['suppliers']),
                'contexto_productos' => count($contexto['products']),
                'elapsed_ms' => $pipelinePhases['clasificacion']['elapsed_ms'],
            ]);

            // ── FASE 3: Análisis (Nova Pro con prompt específico) ──
            $emit('analisis', 'running', null);
            $phaseStart = microtime(true);

            $extracted = $this->analisis->analizar(
                $imageBase64,
                $tipo,
                $perception['labels'],
                $perception['texts'],
                $contexto,
            );

            $pipelinePhases['analisis'] = [
                'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                'success' => $extracted !== null,
                'error' => $extracted === null ? 'Nova Pro returned null — check model access and credentials' : null,
                'model_id' => 'amazon.nova-pro-v1:0',
                'tipo_usado' => $tipo,
            ];

            if (!$extracted) {
                $emit('error', 'error', ['message' => 'Nova Pro no pudo interpretar la imagen']);
                return $this->failedResult($imageUrl, 'No se pudo interpretar la imagen', $startTime, $pipelinePhases);
            }

            // ── Post-processing ──
            $extracted = $this->postProcess($extracted);

            // ── Match sugerencias ──
            $proveedorMatch = null;
            $itemsMatch = [];

            if (!empty($extracted['proveedor'])) {
                $proveedorMatch = $this->sugerencias->matchProveedor($extracted['proveedor']);
                if ($proveedorMatch && $proveedorMatch['score'] >= 70) {
                    $extracted['proveedor'] = $proveedorMatch['nombre_original'];
                }
            }

            if (!empty($extracted['items'])) {
                $itemsMatch = $this->sugerencias->matchItems($extracted['items']);

                if ($this->isProveedorSuspect($extracted['proveedor'])) {
                    $inferredProv = $this->inferProveedorFromItems($itemsMatch);
                    if ($inferredProv) {
                        $extracted['proveedor'] = $inferredProv;
                        $extracted['notas_ia'] = ($extracted['notas_ia'] ?? '') . ' [Proveedor inferido del ingrediente]';
                        $proveedorMatch = $this->sugerencias->matchProveedor($inferredProv);
                    }
                }

                // Re-match items after post-processing
                $itemsMatch = $this->sugerencias->matchItems($extracted['items']);
                $this->applyProductEquivalences($extracted, $itemsMatch);
            }

            // ── Confidence scores ──
            $confidenceScores = $this->calculateConfidence($extracted);
            $overallConfidence = $this->calculateOverallConfidence($confidenceScores);

            // ── Save log ──
            $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $log = AiExtractionLog::create([
                'image_url' => $imageUrl,
                'raw_response' => ['pipeline_phases' => $pipelinePhases],
                'extracted_data' => $extracted,
                'confidence_scores' => $confidenceScores,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
                'model_id' => 'pipeline:rekognition+nova-micro+nova-pro',
                'status' => 'success',
                'error_message' => null,
            ]);

            $result = [
                'success' => true,
                'extraction_log_id' => $log->id,
                'data' => $extracted,
                'confianza' => $confidenceScores,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
                'pipeline_phases' => $pipelinePhases,
                'sugerencias' => [
                    'proveedor' => $proveedorMatch,
                    'items' => $itemsMatch,
                ],
            ];

            $emit('completado', 'done', [
                'proveedor' => $extracted['proveedor'] ?? null,
                'items_count' => count($extracted['items'] ?? []),
                'monto_total' => $extracted['monto_total'] ?? null,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('[Pipeline] Error: ' . $e->getMessage());
            $emit('error', 'error', ['message' => $e->getMessage()]);
            return $this->failedResult($imageUrl, $e->getMessage(), $startTime, $pipelinePhases ?? []);
        }
    }

    // ─── Private helpers ───

    /**
     * Check if Gemini API is available (GOOGLE_API_KEY configured).
     */
    private function isGeminiAvailable(): bool
    {
        return !empty(env('GOOGLE_API_KEY', env('google_api_key')));
    }

    /**
     * Execute the Gemini 2-phase pipeline (clasificacion + analisis).
     */
    private function ejecutarGemini(string $imageUrl, ?callable $onEvent): array
    {
        $startTime = microtime(true);
        $emit = function (string $fase, string $status, ?array $data) use ($onEvent, $startTime): void {
            if ($onEvent) {
                $onEvent($fase, $status, $data, $startTime);
            }
        };

        $pipelinePhases = [];

        try {
            $imageBase64 = $this->getImageBase64($imageUrl);

            if (!$imageBase64) {
                $emit('error', 'error', ['message' => 'No se pudo obtener la imagen']);
                return $this->failedResultGemini($imageUrl, 'No se pudo obtener la imagen', $startTime);
            }

            // ── PHASE 1: Clasificación (Gemini multimodal) ──
            $emit('clasificacion', 'running', ['engine' => 'gemini']);
            $phaseStart = microtime(true);

            $classification = $this->gemini->clasificar($imageBase64);

            if (!$classification) {
                // Fallback: set tipo=desconocido since we have no Rekognition data
                $tipo = 'desconocido';
                $confianza = 0.3;
                $razon = 'Gemini classification failed';
                $tokensClasificacion = ['prompt' => 0, 'candidates' => 0, 'total' => 0];
            } else {
                $tipo = $classification['tipo_imagen'];
                $confianza = $classification['confianza'];
                $razon = $classification['razon'];
                $tokensClasificacion = $classification['tokens'];
            }

            $contexto = $this->clasificador->cargarContexto($tipo);

            $pipelinePhases['clasificacion'] = [
                'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                'tipo' => $tipo,
                'confianza' => $confianza,
                'razon' => $razon,
                'engine' => 'gemini',
                'tokens' => $tokensClasificacion,
            ];

            $emit('clasificacion', 'done', [
                'tipo_imagen' => $tipo,
                'confianza' => $confianza,
                'razon' => $razon,
                'engine' => 'gemini',
                'tokens' => $tokensClasificacion['total'],
                'contexto_proveedores' => count($contexto['suppliers']),
                'contexto_productos' => count($contexto['products']),
                'elapsed_ms' => $pipelinePhases['clasificacion']['elapsed_ms'],
            ]);

            // ── PHASE 2: Análisis (Gemini multimodal con contexto) ──
            $emit('analisis', 'running', ['engine' => 'gemini']);
            $phaseStart = microtime(true);

            $analysisResult = $this->gemini->analizar($imageBase64, $tipo, $contexto);

            if (!$analysisResult) {
                $pipelinePhases['analisis'] = [
                    'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                    'success' => false,
                    'error' => 'Gemini analysis returned null',
                    'engine' => 'gemini',
                ];
                $emit('error', 'error', ['message' => 'Gemini no pudo interpretar la imagen', 'engine' => 'gemini']);
                return $this->failedResultGemini($imageUrl, 'No se pudo interpretar la imagen', $startTime, $pipelinePhases);
            }

            $extracted = $analysisResult['data'];
            $tokensAnalisis = $analysisResult['tokens'];

            $pipelinePhases['analisis'] = [
                'elapsed_ms' => (int) round((microtime(true) - $phaseStart) * 1000),
                'success' => true,
                'engine' => 'gemini',
                'model_id' => 'gemini-2.5-flash-lite',
                'tipo_usado' => $tipo,
                'tokens' => $tokensAnalisis,
            ];

            $emit('analisis', 'done', [
                'engine' => 'gemini',
                'tokens' => $tokensAnalisis['total'],
                'elapsed_ms' => $pipelinePhases['analisis']['elapsed_ms'],
            ]);

            // ── Post-processing (same as AWS path) ──
            $extracted = $this->postProcess($extracted);

            // ── Match sugerencias (same as AWS path) ──
            $proveedorMatch = null;
            $itemsMatch = [];

            if (!empty($extracted['proveedor'])) {
                $proveedorMatch = $this->sugerencias->matchProveedor($extracted['proveedor']);
                if ($proveedorMatch && $proveedorMatch['score'] >= 70) {
                    $extracted['proveedor'] = $proveedorMatch['nombre_original'];
                }
            }

            if (!empty($extracted['items'])) {
                $itemsMatch = $this->sugerencias->matchItems($extracted['items']);

                if ($this->isProveedorSuspect($extracted['proveedor'])) {
                    $inferredProv = $this->inferProveedorFromItems($itemsMatch);
                    if ($inferredProv) {
                        $extracted['proveedor'] = $inferredProv;
                        $extracted['notas_ia'] = ($extracted['notas_ia'] ?? '') . ' [Proveedor inferido del ingrediente]';
                        $proveedorMatch = $this->sugerencias->matchProveedor($inferredProv);
                    }
                }

                $itemsMatch = $this->sugerencias->matchItems($extracted['items']);
                $this->applyProductEquivalences($extracted, $itemsMatch);
            }

            // ── Confidence scores ──
            $confidenceScores = $this->calculateConfidence($extracted);
            $overallConfidence = $this->calculateOverallConfidence($confidenceScores);

            // ── Token tracking and cost (Task 4.3) ──
            $totalPromptTokens = $tokensClasificacion['prompt'] + $tokensAnalisis['prompt'];
            $totalCandidatesTokens = $tokensClasificacion['candidates'] + $tokensAnalisis['candidates'];
            $totalTokens = $tokensClasificacion['total'] + $tokensAnalisis['total'];
            $estimatedCostUsd = ($totalPromptTokens * 0.10 + $totalCandidatesTokens * 0.40) / 1_000_000;

            // ── Save log ──
            $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);
            $log = AiExtractionLog::create([
                'image_url' => $imageUrl,
                'raw_response' => [
                    'pipeline_phases' => $pipelinePhases,
                    'tokens' => [
                        'clasificacion' => $tokensClasificacion,
                        'analisis' => $tokensAnalisis,
                        'total' => [
                            'prompt' => $totalPromptTokens,
                            'candidates' => $totalCandidatesTokens,
                            'total' => $totalTokens,
                        ],
                    ],
                    'estimated_cost_usd' => round($estimatedCostUsd, 6),
                    'engine' => 'gemini',
                ],
                'extracted_data' => $extracted,
                'confidence_scores' => $confidenceScores,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
                'model_id' => 'gemini-2.5-flash-lite',
                'status' => 'success',
                'error_message' => null,
            ]);

            $result = [
                'success' => true,
                'extraction_log_id' => $log->id,
                'data' => $extracted,
                'confianza' => $confidenceScores,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
                'pipeline_phases' => $pipelinePhases,
                'sugerencias' => [
                    'proveedor' => $proveedorMatch,
                    'items' => $itemsMatch,
                ],
            ];

            $emit('completado', 'done', [
                'proveedor' => $extracted['proveedor'] ?? null,
                'items_count' => count($extracted['items'] ?? []),
                'monto_total' => $extracted['monto_total'] ?? null,
                'overall_confidence' => $overallConfidence,
                'processing_time_ms' => $processingTimeMs,
                'engine' => 'gemini',
                'tokens_total' => $totalTokens,
                'estimated_cost_usd' => round($estimatedCostUsd, 6),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('[Pipeline:Gemini] Error: ' . $e->getMessage());
            $emit('error', 'error', ['message' => $e->getMessage(), 'engine' => 'gemini']);
            return $this->failedResultGemini($imageUrl, $e->getMessage(), $startTime, $pipelinePhases ?? []);
        }
    }

    /**
     * Failed result for Gemini pipeline.
     */
    private function failedResultGemini(string $imageUrl, string $error, float $startTime, array $pipelinePhases = []): array
    {
        $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        try {
            AiExtractionLog::create([
                'image_url' => $imageUrl,
                'raw_response' => ['pipeline_phases' => $pipelinePhases, 'engine' => 'gemini'],
                'extracted_data' => [],
                'confidence_scores' => [],
                'overall_confidence' => 0,
                'processing_time_ms' => $processingTimeMs,
                'model_id' => 'gemini-2.5-flash-lite',
                'status' => 'failed',
                'error_message' => $error,
            ]);
        } catch (\Exception $e) {
            Log::warning('[Pipeline:Gemini] Failed to save error log: ' . $e->getMessage());
        }

        return [
            'success' => false,
            'error' => $error,
            'fallback' => 'manual',
        ];
    }

    private function resolveS3Key(string $imageUrl): ?string
    {
        if (!str_starts_with($imageUrl, 'http')) {
            return $imageUrl;
        }

        $bucket = config('filesystems.disks.s3.bucket', env('AWS_BUCKET', 'laruta11-images'));
        $prefix = "https://{$bucket}.s3.amazonaws.com/";
        if (str_starts_with($imageUrl, $prefix)) {
            return substr($imageUrl, strlen($prefix));
        }

        return null;
    }

    private function getImageBase64(string $imageUrl): ?string
    {
        try {
            if (!str_starts_with($imageUrl, 'http')) {
                $contents = Storage::disk('s3')->get($imageUrl);
                return $contents ? base64_encode($contents) : null;
            }

            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($imageUrl);
            return $response->successful() ? base64_encode($response->body()) : null;
        } catch (\Exception $e) {
            Log::warning('[Pipeline] Failed to get image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Apply all post-extraction rules (moved from ExtraccionController).
     */
    private function postProcess(array $data): array
    {
        $data = $this->normalizeFecha($data);
        $data = $this->mapPersonToSupplier($data);
        $data = $this->matchProveedorByRut($data);
        $data = $this->applySupplierRules($data);
        return $data;
    }

    /**
     * Normalize fecha: fallback to today if null, empty, or looks like a packaging date.
     */
    private function normalizeFecha(array $data): array
    {
        $fecha = $data['fecha'] ?? null;
        $today = date('Y-m-d');

        if (empty($fecha)) {
            $data['fecha'] = $today;
            $data['notas_ia'] = ($data['notas_ia'] ?? '') . ' [Fecha: hoy (no detectada)]';
            return $data;
        }

        // Validate date format and reject packaging/expiry dates (year too old or too future)
        $parsed = date_create($fecha);
        if (!$parsed) {
            $data['fecha'] = $today;
            $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Fecha '{$fecha}' inválida, usando hoy]";
            return $data;
        }

        $year = (int) $parsed->format('Y');
        $currentYear = (int) date('Y');

        // If year is more than 1 year in the past or in the future, it's likely a packaging date
        if ($year < $currentYear - 1 || $year > $currentYear + 1) {
            $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Fecha '{$fecha}' parece empaque/vencimiento, usando hoy]";
            $data['fecha'] = $today;
        }

        return $data;
    }

    private function mapPersonToSupplier(array $data): array
    {
        $personToSupplier = [
            'karen miranda olmedo' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'karen miranda' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'elcia vilca' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'eliana vilca' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'cecilia rojas hinojosa' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'cecilia rojas' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'maria mondañez mamani' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'maria mondanez mamani' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'giovanna loza salas' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'giovanna loza' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'ariel araya' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'ariel aliro araya villalobos' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'karina roco' => ['proveedor' => 'ARIAKA', 'item' => 'Servicios Delivery', 'tipo_compra' => 'otros'],
            'elton san martin' => ['proveedor' => 'Abastible', 'item' => 'gas 15', 'tipo_compra' => 'ingredientes'],
            'elton san martín' => ['proveedor' => 'Abastible', 'item' => 'gas 15', 'tipo_compra' => 'ingredientes'],
            'karina andrea muñoz ahumada' => ['proveedor' => 'Ariztía (proveedor)', 'item' => null, 'tipo_compra' => 'ingredientes'],
            'karina muñoz' => ['proveedor' => 'Ariztía (proveedor)', 'item' => null, 'tipo_compra' => 'ingredientes'],
            'lucila cacera' => ['proveedor' => 'agro-lucila', 'item' => null, 'tipo_compra' => 'ingredientes'],
            'ricardo huiscaleo' => null,
            'ricardo aníbal huiscaleo' => null,
            'ricardo aníbal huiscaleo llafquén' => null,
            'ricardo anibal huiscaleo llafquen' => null,
        ];

        $proveedor = mb_strtolower(trim($data['proveedor'] ?? ''));

        foreach ($personToSupplier as $person => $mapping) {
            if (str_contains($proveedor, $person) || (similar_text($proveedor, $person, $pct) && $pct > 80)) {
                if ($mapping === null) {
                    $data['proveedor'] = null;
                    $data['notas_ia'] = ($data['notas_ia'] ?? '') . ' [IA detectó al emisor como proveedor, corregido]';
                } else {
                    $data['proveedor'] = $mapping['proveedor'];
                    $data['metodo_pago'] = 'transfer';
                    $data['tipo_compra'] = $mapping['tipo_compra'];
                    if ($mapping['item'] && empty($data['items'])) {
                        $montoTotal = (float) ($data['monto_total'] ?? 0);
                        $cantidad = 1;
                        $precioUnitario = $montoTotal;

                        if (str_contains($mapping['item'], 'gas')) {
                            $precioCilindro = 23500;
                            if ($montoTotal >= $precioCilindro * 1.5) {
                                $cantidad = (int) round($montoTotal / $precioCilindro);
                                $precioUnitario = (int) round($montoTotal / $cantidad);
                            }
                        }

                        $data['items'] = [[
                            'nombre' => $mapping['item'],
                            'cantidad' => $cantidad,
                            'unidad' => 'unidad',
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => (int) $montoTotal,
                        ]];
                    } elseif ($mapping['item'] && str_contains($mapping['item'], 'gas')) {
                        $montoTotal = (float) ($data['monto_total'] ?? 0);
                        $cantidad = 1;
                        $precioCilindro = 23500;
                        if ($montoTotal >= $precioCilindro * 1.5) {
                            $cantidad = (int) round($montoTotal / $precioCilindro);
                        }
                        $precioUnitario = $cantidad > 0 ? (int) round($montoTotal / $cantidad) : (int) $montoTotal;
                        $data['items'] = [[
                            'nombre' => $mapping['item'],
                            'cantidad' => $cantidad,
                            'unidad' => 'unidad',
                            'precio_unitario' => $precioUnitario,
                            'subtotal' => (int) $montoTotal,
                        ]];
                    } elseif ($mapping['item']) {
                        foreach ($data['items'] as &$item) {
                            if (empty($item['nombre']) || mb_strtolower($item['nombre']) === 'transferencia') {
                                $item['nombre'] = $mapping['item'];
                            }
                        }
                        unset($item);
                    }
                }
                break;
            }
        }

        if (mb_strtolower(trim($data['proveedor'] ?? '')) === 'mercado pago') {
            $data['proveedor'] = null;
            $data['metodo_pago'] = 'transfer';
        }

        $proveedorNow = mb_strtolower(trim($data['proveedor'] ?? ''));
        if (str_contains($proveedorNow, 'ariaka')) {
            $data['proveedor'] = 'ARIAKA';
            $data['metodo_pago'] = 'transfer';
            $data['tipo_compra'] = 'otros';
            if (!empty($data['items'])) {
                foreach ($data['items'] as &$item) {
                    $itemName = mb_strtolower($item['nombre'] ?? '');
                    if (empty($itemName) || $itemName === 'transferencia' || str_contains($itemName, 'servicio')) {
                        $item['nombre'] = 'Servicios Delivery';
                    }
                }
                unset($item);
            }
        }

        return $data;
    }

    private function matchProveedorByRut(array $data): array
    {
        $rut = $data['rut_proveedor'] ?? null;
        if (!empty($rut)) {
            $supplier = SupplierIndex::where('rut', $rut)->first();
            if ($supplier) {
                $data['proveedor'] = $supplier->nombre_original;
                $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Proveedor por RUT {$rut}]";
                return $data;
            }
        }

        $knownPatterns = [
            'ariztia' => 'Ariztía (proveedor)',
            'agrosuper' => 'Agrosuper',
            'ideal' => 'ideal',
            'shipo' => 'Shipo',
            'cencosud' => 'Jumbo',
            'santa isabel' => 'Santa Isabel',
            'jumbo' => 'Jumbo',
            'vanni' => 'vanni',
        ];

        $allText = mb_strtolower(
            ($data['proveedor'] ?? '') . ' ' .
            ($data['notas_ia'] ?? '') . ' ' .
            ($data['rut_proveedor'] ?? '')
        );
        foreach ($data['items'] ?? [] as $item) {
            $allText .= ' ' . mb_strtolower($item['nombre'] ?? '');
        }

        foreach ($knownPatterns as $pattern => $supplierName) {
            if (str_contains($allText, $pattern)) {
                $currentProv = mb_strtolower($data['proveedor'] ?? '');
                if (!str_contains($currentProv, $pattern)) {
                    $data['proveedor'] = $supplierName;
                    $data['notas_ia'] = ($data['notas_ia'] ?? '') . " [Proveedor detectado por patrón '{$pattern}']";
                }
                break;
            }
        }

        return $data;
    }

    private function applySupplierRules(array $data): array
    {
        $proveedor = mb_strtolower(trim($data['proveedor'] ?? ''));
        if ($proveedor === '') {
            return $data;
        }

        $transferSuppliers = [
            'ariztía', 'ariztia', 'ariztía (proveedor)', 'ariztia (proveedor)',
            'agrosuper', 'agrosuper (proveedor)',
            'ideal', 'agro-lucila', 'ariaka', 'jumboapp', 'vanni',
        ];
        foreach ($transferSuppliers as $ts) {
            if (str_contains($proveedor, $ts)) {
                $data['metodo_pago'] = 'transfer';
                break;
            }
        }

        $ingredientSuppliers = ['ariztía', 'ariztia', 'agrosuper', 'ideal', 'agro-lucila'];
        foreach ($ingredientSuppliers as $is) {
            if (str_contains($proveedor, $is)) {
                $data['tipo_compra'] = 'ingredientes';
                break;
            }
        }

        return $data;
    }

    private function isProveedorSuspect(?string $proveedor): bool
    {
        if (empty($proveedor)) {
            return true;
        }

        $suspects = ['yumbel', 'arica', 'la ruta', 'ruta 11', 'ricardo', 'proveedor desconocido', 'desconocido', 'generico'];
        $lower = mb_strtolower(trim($proveedor));

        foreach ($suspects as $s) {
            if (str_contains($lower, $s)) {
                return true;
            }
        }

        $match = $this->sugerencias->matchProveedor($proveedor);
        return !$match || $match['score'] < 60;
    }

    private function inferProveedorFromItems(array $itemsMatch): ?string
    {
        $supplierCounts = [];

        foreach ($itemsMatch as $im) {
            if (!($im['pre_selected'] ?? false) || !($im['match'] ?? null)) {
                continue;
            }
            $matchId = $im['match']['id'] ?? null;
            $matchType = $im['match_type'] ?? 'ingredient';

            if ($matchType === 'ingredient' && $matchId) {
                $supplier = Ingredient::where('id', $matchId)->value('supplier');
                if (!empty($supplier)) {
                    $supplierCounts[$supplier] = ($supplierCounts[$supplier] ?? 0) + 1;
                }
            }
        }

        if (empty($supplierCounts)) {
            return null;
        }

        arsort($supplierCounts);
        return array_key_first($supplierCounts);
    }

    /**
     * Apply product equivalences to convert package quantities to individual units.
     */
    private function applyProductEquivalences(array &$data, array &$itemsMatch): void
    {
        if (empty($data['items'])) {
            return;
        }

        foreach ($data['items'] as $idx => &$item) {
            $itemName = mb_strtolower(trim($item['nombre'] ?? ''));
            if ($itemName === '') {
                continue;
            }

            $itemNorm = $this->removeAccents($itemName);

            $equiv = ProductEquivalence::where('nombre_normalizado', $itemName)->first();
            if (!$equiv) {
                $equiv = ProductEquivalence::get()->first(function ($eq) use ($itemName, $itemNorm) {
                    $eqNorm = $this->removeAccents(mb_strtolower($eq->nombre_normalizado));
                    return str_contains($itemNorm, $eqNorm)
                        || str_contains($eqNorm, $itemNorm)
                        || str_contains($itemName, mb_strtolower($eq->nombre_normalizado))
                        || str_contains(mb_strtolower($eq->nombre_normalizado), $itemName);
                });
            }

            if ($equiv) {
                // Skip equivalence if item already has a base unit (kg, g, unidad, litro, ml)
                // Equivalences are for converting packages (caja, saco, paquete, bidón) to base units
                $baseUnits = ['kg', 'g', 'unidad', 'litro', 'ml', 'l'];
                $itemUnit = mb_strtolower(trim($item['unidad'] ?? ''));
                $equivVisual = mb_strtolower(trim($equiv->unidad_visual ?? ''));

                if (in_array($itemUnit, $baseUnits, true) && !in_array($equivVisual, $baseUnits, true)) {
                    // Item is already in base units, don't apply package conversion
                    // But still link to the ingredient if possible
                    if (isset($itemsMatch[$idx]) && $equiv->ingrediente_id) {
                        $ing = Ingredient::find($equiv->ingrediente_id);
                        if ($ing) {
                            $itemsMatch[$idx]['match'] = [
                                'id' => $ing->id,
                                'name' => $ing->name,
                                'unit' => $ing->unit,
                                'cost_per_unit' => $ing->cost_per_unit,
                                'current_stock' => $ing->current_stock,
                            ];
                            $itemsMatch[$idx]['match_type'] = 'ingredient';
                            $itemsMatch[$idx]['score'] = 100;
                            $itemsMatch[$idx]['pre_selected'] = true;
                        }
                    }
                    continue;
                }

                $originalQty = (float) ($item['cantidad'] ?? 1);
                $item['cantidad'] = $originalQty * (float) $equiv->cantidad_por_unidad;
                $item['unidad'] = $equiv->unidad_real;
                if ($item['cantidad'] > 0) {
                    $item['precio_unitario'] = (int) round(($item['subtotal'] ?? 0) / $item['cantidad']);
                }
                $item['empaque_detalle'] = "{$originalQty} {$equiv->unidad_visual} × {$equiv->cantidad_por_unidad} {$equiv->unidad_real}/{$equiv->unidad_visual} = {$item['cantidad']} {$equiv->unidad_real}";

                if (isset($itemsMatch[$idx]) && $equiv->ingrediente_id) {
                    $ing = Ingredient::find($equiv->ingrediente_id);
                    if ($ing) {
                        $itemsMatch[$idx]['match'] = [
                            'id' => $ing->id,
                            'name' => $ing->name,
                            'unit' => $ing->unit,
                            'cost_per_unit' => $ing->cost_per_unit,
                            'current_stock' => $ing->current_stock,
                        ];
                        $itemsMatch[$idx]['match_type'] = 'ingredient';
                        $itemsMatch[$idx]['score'] = 100;
                        $itemsMatch[$idx]['pre_selected'] = true;
                    }
                }
            }
        }
        unset($item);
    }

    private function removeAccents(string $str): string
    {
        return strtr($str, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ñ' => 'n', 'ü' => 'u', 'Á' => 'a', 'É' => 'e', 'Í' => 'i',
            'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n', 'Ü' => 'u',
        ]);
    }

    private function calculateConfidence(array $extracted): array
    {
        $scores = [
            'proveedor' => 0.0, 'rut' => 0.0, 'items' => 0.0,
            'monto_neto' => 0.0, 'iva' => 0.0, 'monto_total' => 0.0,
            'tipo_imagen' => 0.0, 'peso_bascula' => 0.0,
        ];

        $tipoImagen = $extracted['tipo_imagen'] ?? 'desconocido';
        $scores['tipo_imagen'] = in_array($tipoImagen, ['boleta', 'factura', 'producto', 'bascula'], true) ? 0.9 : 0.3;

        if (!empty($extracted['proveedor']) && is_string($extracted['proveedor'])) {
            $scores['proveedor'] = strlen($extracted['proveedor']) > 2 ? 0.9 : 0.5;
        }

        if (!empty($extracted['rut_proveedor'])) {
            $scores['rut'] = preg_match('/^\d{1,2}\.\d{3}\.\d{3}-[\dkK]$/', $extracted['rut_proveedor']) ? 0.95 : 0.4;
        }

        if (!empty($extracted['items']) && is_array($extracted['items'])) {
            $validItems = 0;
            foreach ($extracted['items'] as $item) {
                if (!empty($item['nombre']) && isset($item['cantidad'])) {
                    if (in_array($tipoImagen, ['producto', 'bascula'], true) || isset($item['precio_unitario'])) {
                        $validItems++;
                    }
                }
            }
            $total = count($extracted['items']);
            $scores['items'] = $total > 0 ? round($validItems / $total, 2) : 0.0;
        }

        if ($tipoImagen === 'bascula' && isset($extracted['peso_bascula']) && is_numeric($extracted['peso_bascula'])) {
            $scores['peso_bascula'] = $extracted['peso_bascula'] > 0 ? 0.85 : 0.3;
        }

        if (in_array($tipoImagen, ['boleta', 'factura'], true)) {
            if (isset($extracted['monto_neto']) && is_numeric($extracted['monto_neto']) && $extracted['monto_neto'] > 0) {
                $scores['monto_neto'] = 0.9;
            }
            if (isset($extracted['iva']) && is_numeric($extracted['iva']) && $extracted['iva'] > 0) {
                if (isset($extracted['monto_neto']) && $extracted['monto_neto'] > 0) {
                    $expectedIva = round($extracted['monto_neto'] * 0.19);
                    $diff = abs($extracted['iva'] - $expectedIva);
                    $scores['iva'] = $diff <= max(1, $expectedIva * 0.02) ? 0.95 : 0.6;
                } else {
                    $scores['iva'] = 0.7;
                }
            }
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

    private function calculateOverallConfidence(array $scores): float
    {
        $weights = [
            'proveedor' => 0.15, 'rut' => 0.05, 'items' => 0.35,
            'monto_neto' => 0.15, 'iva' => 0.10, 'monto_total' => 0.20,
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

    private function failedResult(string $imageUrl, string $error, float $startTime, array $pipelinePhases = []): array
    {
        $processingTimeMs = (int) round((microtime(true) - $startTime) * 1000);

        try {
            AiExtractionLog::create([
                'image_url' => $imageUrl,
                'raw_response' => ['pipeline_phases' => $pipelinePhases],
                'extracted_data' => [],
                'confidence_scores' => [],
                'overall_confidence' => 0,
                'processing_time_ms' => $processingTimeMs,
                'model_id' => 'pipeline:rekognition+nova-micro+nova-pro',
                'status' => 'failed',
                'error_message' => $error,
            ]);
        } catch (\Exception $e) {
            Log::warning('[Pipeline] Failed to save error log: ' . $e->getMessage());
        }

        return [
            'success' => false,
            'error' => $error,
            'fallback' => 'manual',
        ];
    }
}
