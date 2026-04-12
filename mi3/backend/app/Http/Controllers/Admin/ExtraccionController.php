<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compra\ExtraccionService;
use App\Services\Compra\PipelineService;
use App\Services\Compra\SugerenciaService;
use App\Services\Compra\ValidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExtraccionController extends Controller
{
    public function __construct(
        private ExtraccionService $extraccionService,
        private SugerenciaService $sugerenciaService,
        private ValidacionService $validacionService,
        private PipelineService $pipelineService,
    ) {}

    /**
     * Extract data from an image using IA, then match with suggestions.
     * POST /api/v1/admin/compras/extract
     */
    public function extract(Request $request): JsonResponse
    {
        $request->validate([
            'image_url' => 'required_without:temp_key|string',
            'temp_key' => 'required_without:image_url|string',
        ]);

        $imageUrl = $request->input('image_url') ?? $request->input('temp_key');

        try {
            $result = $this->extraccionService->extractFromImage($imageUrl);

            if (!($result['success'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'] ?? 'Extracción falló',
                    'fallback' => 'manual',
                ], 422);
            }

            // Match proveedor and items with suggestions
            $data = $result['data'];
            $proveedorMatch = null;
            $itemsMatch = [];

            if (!empty($data['proveedor'])) {
                $proveedorMatch = $this->sugerenciaService->matchProveedor($data['proveedor']);
            }

            if (!empty($data['items'])) {
                $itemsMatch = $this->sugerenciaService->matchItems($data['items']);
            }

            return response()->json([
                'success' => true,
                'extraction_log_id' => $result['extraction_log_id'],
                'data' => $data,
                'confianza' => $result['confianza'],
                'overall_confidence' => $result['overall_confidence'],
                'processing_time_ms' => $result['processing_time_ms'],
                'sugerencias' => [
                    'proveedor' => $proveedorMatch,
                    'items' => $itemsMatch,
                ],
            ]);
        } catch (\RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'tiempo de espera') ? 408 : 500;
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
