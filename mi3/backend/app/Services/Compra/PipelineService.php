<?php

namespace App\Services\Compra;

use App\Models\AiTrainingDataset;
use App\Models\Compra;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PipelineService
{
    private int $batchSize = 10;

    public function __construct(
        private ExtraccionService $extraccionService,
        private ValidacionService $validacionService,
        private SugerenciaService $sugerenciaService,
    ) {}

    /**
     * Process historical S3 images in batches of 10.
     * For each: extract with IA, compare with real data, save to ai_training_dataset.
     *
     * @return array Batch processing report
     */
    public function ejecutar(): array
    {
        $batchId = 'batch_' . date('Ymd_His') . '_' . Str::random(6);
        $processed = 0;
        $succeeded = 0;
        $failed = 0;
        $skipped = 0;
        $results = [];

        // Get compras that have images and haven't been processed yet
        $compras = Compra::with('detalles')
            ->whereNotNull('imagen_respaldo')
            ->whereRaw("JSON_LENGTH(imagen_respaldo) > 0")
            ->whereNotIn('id', function ($q) {
                $q->select('compra_id')->from('ai_training_dataset');
            })
            ->orderBy('fecha_compra', 'desc')
            ->limit($this->batchSize)
            ->get();

        if ($compras->isEmpty()) {
            return [
                'batch_id' => $batchId,
                'message' => 'No hay imágenes pendientes de procesar',
                'processed' => 0,
                'succeeded' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        foreach ($compras as $compra) {
            $imageUrls = $compra->imagen_respaldo ?? [];
            $imageUrl = $imageUrls[0] ?? null;

            if (!$imageUrl) {
                $skipped++;
                continue;
            }

            try {
                // Build real data from compra
                $realData = $this->buildRealData($compra);

                // Extract with IA
                $extraction = $this->extraccionService->extractFromImage($imageUrl);

                $extractedData = null;
                $precisionScores = null;
                $overallPrecision = null;
                $extractionLogId = null;

                if ($extraction['success'] ?? false) {
                    $extractedData = $extraction['data'];
                    $extractionLogId = $extraction['extraction_log_id'] ?? null;

                    // Compare with real data
                    $comparison = $this->validacionService->compararExtraccion($extractedData, $realData);
                    $precisionScores = $comparison;
                    $overallPrecision = $comparison['overall_precision'] ?? 0;
                    $succeeded++;
                } else {
                    $failed++;
                }

                // Save to training dataset
                AiTrainingDataset::create([
                    'compra_id' => $compra->id,
                    'image_url' => $imageUrl,
                    'extraction_log_id' => $extractionLogId,
                    'real_data' => $realData,
                    'extracted_data' => $extractedData,
                    'precision_scores' => $precisionScores,
                    'overall_precision' => $overallPrecision,
                    'processed_at' => now(),
                    'batch_id' => $batchId,
                ]);

                $processed++;

                $results[] = [
                    'compra_id' => $compra->id,
                    'success' => $extraction['success'] ?? false,
                    'overall_precision' => $overallPrecision,
                ];
            } catch (\Exception $e) {
                Log::warning("[PipelineService] Error processing compra {$compra->id}: " . $e->getMessage());
                $failed++;
                $processed++;

                // Save as failed entry
                AiTrainingDataset::create([
                    'compra_id' => $compra->id,
                    'image_url' => $imageUrl,
                    'real_data' => $this->buildRealData($compra),
                    'processed_at' => now(),
                    'batch_id' => $batchId,
                ]);
            }
        }

        // After processing, rebuild supplier index from all historical compras
        $this->rebuildSupplierIndex();

        return [
            'batch_id' => $batchId,
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'skipped' => $skipped,
            'results' => $results,
        ];
    }

    /**
     * Rebuild the supplier_index from ALL historical compras.
     * This ensures the learned patterns are up to date after pipeline runs.
     */
    private function rebuildSupplierIndex(): void
    {
        try {
            $compras = Compra::with('detalles')
                ->whereNotNull('proveedor')
                ->where('proveedor', '!=', '')
                ->orderBy('fecha_compra', 'asc')
                ->get();

            foreach ($compras as $compra) {
                $this->sugerenciaService->actualizarIndice($compra);
            }

            Log::info("[PipelineService] Supplier index rebuilt from {$compras->count()} compras");
        } catch (\Exception $e) {
            Log::warning("[PipelineService] Failed to rebuild supplier index: " . $e->getMessage());
        }
    }

    /**
     * Build real data structure from a Compra model for comparison.
     */
    private function buildRealData(Compra $compra): array
    {
        $items = [];
        foreach ($compra->detalles as $detalle) {
            $items[] = [
                'nombre' => $detalle->nombre_item,
                'cantidad' => (float) $detalle->cantidad,
                'unidad' => $detalle->unidad,
                'precio_unitario' => (int) $detalle->precio_unitario,
                'subtotal' => (int) $detalle->subtotal,
            ];
        }

        return [
            'proveedor' => $compra->proveedor,
            'monto_total' => (int) $compra->monto_total,
            'items' => $items,
        ];
    }

    /**
     * Return the latest batch report.
     *
     * @return array Report with batch info and precision metrics
     */
    public function reporte(): array
    {
        $latestBatch = AiTrainingDataset::whereNotNull('batch_id')
            ->orderBy('processed_at', 'desc')
            ->value('batch_id');

        if (!$latestBatch) {
            return [
                'batch_id' => null,
                'message' => 'No hay batches procesados',
                'total' => 0,
            ];
        }

        $records = AiTrainingDataset::where('batch_id', $latestBatch)->get();

        $total = $records->count();
        $withPrecision = $records->whereNotNull('overall_precision');
        $avgPrecision = $withPrecision->isNotEmpty() ? round($withPrecision->avg('overall_precision'), 2) : 0;

        $succeeded = $withPrecision->count();
        $failed = $total - $succeeded;

        return [
            'batch_id' => $latestBatch,
            'processed_at' => $records->max('processed_at'),
            'total' => $total,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'avg_precision' => $avgPrecision,
            'records' => $records->map(fn ($r) => [
                'compra_id' => $r->compra_id,
                'overall_precision' => $r->overall_precision,
                'precision_scores' => $r->precision_scores,
            ])->toArray(),
        ];
    }
}
