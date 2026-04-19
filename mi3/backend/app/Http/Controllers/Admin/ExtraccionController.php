<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compra\AsistenteCompraService;
use App\Services\Compra\ExtraccionService;
use App\Services\Compra\PipelineExtraccionService;
use App\Services\Compra\PipelineService;
use App\Services\Compra\SugerenciaService;
use App\Services\Compra\ValidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExtraccionController extends Controller
{
    public function __construct(
        private ExtraccionService $extraccionService,
        private PipelineExtraccionService $pipelineExtraccionService,
        private SugerenciaService $sugerenciaService,
        private ValidacionService $validacionService,
        private PipelineService $pipelineService,
        private AsistenteCompraService $asistenteService,
    ) {}

    /**
     * Extract data from an image using the multi-model pipeline (synchronous).
     * POST /api/v1/admin/compras/extract
     *
     * Backward-compatible: same request/response format as before.
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required_without:temp_key|string',
            'temp_key' => 'required_without:image_url|string',
        ]);

        $imageUrl = $request->input('image_url') ?? $request->input('temp_key');

        try {
            $result = $this->pipelineExtraccionService->ejecutar($imageUrl);

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Extracción falló',
                    'fallback' => 'manual',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'extraction_log_id' => $result['extraction_log_id'],
                'data' => $result['data'],
                'confianza' => $result['confianza'],
                'overall_confidence' => $result['overall_confidence'],
                'processing_time_ms' => $result['processing_time_ms'],
                'sugerencias' => $result['sugerencias'],
            ]);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'timed out') ? 408 : 500;
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'fallback' => 'manual',
            ], $status);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en extracción: ' . $e->getMessage(),
                'fallback' => 'manual',
            ], 500);
        }
    }

    /**
     * Extract data using the multi-model pipeline with SSE streaming.
     * POST /api/v1/admin/compras/extract-pipeline
     *
     * Returns Server-Sent Events with progress for each phase.
     */
    public function extractPipeline(Request $request): StreamedResponse
    {
        $request->validate([
            'image_url' => 'required_without:temp_key|string',
            'temp_key' => 'required_without:image_url|string',
        ]);

        $imageUrl = $request->input('image_url') ?? $request->input('temp_key');

        return response()->stream(function () use ($imageUrl) {
            $result = $this->pipelineExtraccionService->ejecutar(
                $imageUrl,
                function (string $fase, string $status, ?array $data, float $startTime): void {
                    $elapsed = (int) round((microtime(true) - $startTime) * 1000);
                    $event = [
                        'fase' => $fase,
                        'status' => $status,
                        'data' => $data,
                        'elapsed_ms' => $elapsed,
                    ];
                    // Promote engine to top-level for frontend detection
                    if (isset($data['engine'])) {
                        $event['engine'] = $data['engine'];
                    }
                    echo "data: " . json_encode($event, JSON_UNESCAPED_UNICODE) . "\n\n";
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                },
            );

            // Send final result as last event
            $finalEvent = json_encode([
                'fase' => 'resultado',
                'status' => $result['success'] ? 'done' : 'error',
                'data' => $result,
                'elapsed_ms' => $result['processing_time_ms'] ?? 0,
            ], JSON_UNESCAPED_UNICODE);
            echo "data: {$finalEvent}\n\n";
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * List extraction logs for the debug console.
     * GET /api/v1/admin/compras/extraction-logs
     */
    public function extractionLogs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 50);
        $status = $request->input('status');

        $query = \App\Models\AiExtractionLog::orderBy('created_at', 'desc');

        if ($status && in_array($status, ['success', 'failed', 'partial'], true)) {
            $query->where('status', $status);
        }

        $logs = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'total' => $logs->total(),
            'current_page' => $logs->currentPage(),
            'total_pages' => $logs->lastPage(),
        ]);
    }

    /**
     * Get AI budget stats (tokens, cost, remaining budget).
     * GET /api/v1/admin/compras/ai-budget
     */
    public function aiBudget(): JsonResponse
    {
        $budgetClp = 10000; // CLP 10,000 prepago
        $usdToClp = 950; // approximate rate

        $stats = \App\Models\AiExtractionLog::where(function ($q) {
                $q->where('model_id', 'like', 'gemini%')
                  ->orWhere('model_id', 'like', 'pipeline:%gemini%');
            })
            ->selectRaw('COUNT(*) as total_extractions')
            ->selectRaw("SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_response, '$.tokens.total.prompt')) AS UNSIGNED)) as total_prompt_tokens")
            ->selectRaw("SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_response, '$.tokens.total.candidates')) AS UNSIGNED)) as total_candidates_tokens")
            ->selectRaw("SUM(CAST(JSON_UNQUOTE(JSON_EXTRACT(raw_response, '$.estimated_cost_usd')) AS DECIMAL(10,8))) as total_cost_usd")
            ->first();

        $totalExtractions = (int) ($stats->total_extractions ?? 0);
        $promptTokens = (int) ($stats->total_prompt_tokens ?? 0);
        $candidatesTokens = (int) ($stats->total_candidates_tokens ?? 0);
        $totalCostUsd = (float) ($stats->total_cost_usd ?? 0);
        $totalCostClp = (int) round($totalCostUsd * $usdToClp);
        $remainingClp = $budgetClp - $totalCostClp;
        $budgetPct = $budgetClp > 0 ? round(($totalCostClp / $budgetClp) * 100, 1) : 0;

        return response()->json([
            'success' => true,
            'budget_clp' => $budgetClp,
            'spent_clp' => $totalCostClp,
            'remaining_clp' => max(0, $remainingClp),
            'budget_pct' => $budgetPct,
            'total_cost_usd' => round($totalCostUsd, 6),
            'total_extractions' => $totalExtractions,
            'prompt_tokens' => $promptTokens,
            'candidates_tokens' => $candidatesTokens,
            'total_tokens' => $promptTokens + $candidatesTokens,
            'model' => 'gemini-2.5-flash-lite',
        ]);
    }

    /**
     * Get a single extraction log with full detail.
     * GET /api/v1/admin/compras/extraction-logs/{id}
     */
    public function extractionLogDetail(int $id): JsonResponse
    {
        $log = \App\Models\AiExtractionLog::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    /**
     * Return extraction quality metrics from ai_extraction_logs.
     * GET /api/v1/admin/compras/extraction-quality
     */
    public function quality(): JsonResponse
    {
        $report = $this->validacionService->generarReporte();

        return response()->json([
            'success' => true,
            ...$report,
        ]);
    }

    /**
     * Run the training pipeline.
     * POST /api/v1/admin/compras/pipeline/run
     */
    public function runPipeline(): JsonResponse
    {
        try {
            $result = $this->pipelineService->ejecutar();

            return response()->json([
                'success' => true,
                ...$result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error en pipeline: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Return the latest pipeline report.
     * GET /api/v1/admin/compras/pipeline/report
     */
    public function pipelineReport(): JsonResponse
    {
        $report = $this->pipelineService->reporte();

        return response()->json([
            'success' => true,
            ...$report,
        ]);
    }
}
