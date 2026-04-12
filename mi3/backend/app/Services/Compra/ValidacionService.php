<?php

namespace App\Services\Compra;

use App\Models\AiTrainingDataset;
use Illuminate\Support\Facades\Log;

class ValidacionService
{
    /**
     * Compare extracted data vs real data applying per-field thresholds.
     *
     * Thresholds:
     * - proveedor: ≥ 85% text similarity
     * - monto_total: ≤ 2% difference
     * - item: name ≥ 80% AND qty < 10% AND price < 5%
     *
     * @return array Per-field comparison results with overall precision
     */
    public function compararExtraccion(array $extracted, array $real): array
    {
        $results = [
            'proveedor' => $this->compararProveedor($extracted['proveedor'] ?? '', $real['proveedor'] ?? ''),
            'monto_total' => $this->compararMonto($extracted['monto_total'] ?? 0, $real['monto_total'] ?? 0),
            'items' => $this->compararItems($extracted['items'] ?? [], $real['items'] ?? []),
        ];

        // Calculate overall precision as weighted average
        $weights = ['proveedor' => 0.25, 'monto_total' => 0.25, 'items' => 0.50];
        $weighted = 0;
        foreach ($weights as $field => $weight) {
            $score = $results[$field]['correct'] ? 1.0 : 0.0;
            if ($field === 'items' && isset($results[$field]['precision'])) {
                $score = $results[$field]['precision'];
            }
            $weighted += $score * $weight;
        }

        $results['overall_precision'] = round($weighted * 100, 2);

        return $results;
    }

    /**
     * Compare supplier names using text similarity.
     * Correct if similarity ≥ 85%.
     */
    private function compararProveedor(string $extracted, string $real): array
    {
        if (empty($extracted) || empty($real)) {
            return ['correct' => false, 'similarity' => 0, 'extracted' => $extracted, 'real' => $real];
        }

        similar_text(mb_strtolower(trim($extracted)), mb_strtolower(trim($real)), $percent);

        return [
            'correct' => $percent >= 85,
            'similarity' => round($percent, 2),
            'extracted' => $extracted,
            'real' => $real,
        ];
    }

    /**
     * Compare total amounts.
     * Correct if absolute difference ≤ 2% of real amount.
     */
    private function compararMonto(float $extracted, float $real): array
    {
        if ($real == 0) {
            return ['correct' => $extracted == 0, 'difference_pct' => 0, 'extracted' => $extracted, 'real' => $real];
        }

        $diffPct = abs($extracted - $real) / abs($real) * 100;

        return [
            'correct' => $diffPct <= 2,
            'difference_pct' => round($diffPct, 2),
            'extracted' => $extracted,
            'real' => $real,
        ];
    }

    /**
     * Compare item lists.
     * An item is correct if: name similarity ≥ 80% AND qty diff < 10% AND price diff < 5%.
     */
    private function compararItems(array $extractedItems, array $realItems): array
    {
        if (empty($realItems)) {
            return ['correct' => empty($extractedItems), 'precision' => empty($extractedItems) ? 1.0 : 0.0, 'details' => []];
        }

        $matched = 0;
        $details = [];

        foreach ($realItems as $realItem) {
            $bestMatch = null;
            $bestScore = 0;

            $realName = mb_strtolower(trim($realItem['nombre'] ?? $realItem['nombre_item'] ?? ''));
            $realQty = (float) ($realItem['cantidad'] ?? 0);
            $realPrice = (float) ($realItem['precio_unitario'] ?? 0);

            foreach ($extractedItems as $idx => $extItem) {
                $extName = mb_strtolower(trim($extItem['nombre'] ?? ''));
                similar_text($realName, $extName, $namePct);

                if ($namePct > $bestScore) {
                    $bestScore = $namePct;
                    $bestMatch = $extItem;
                    $bestMatch['_idx'] = $idx;
                    $bestMatch['_name_similarity'] = $namePct;
                }
            }

            if (!$bestMatch || $bestScore < 80) {
                $details[] = [
                    'real' => $realItem,
                    'extracted' => $bestMatch,
                    'correct' => false,
                    'reason' => 'name_mismatch',
                    'name_similarity' => $bestScore,
                ];
                continue;
            }

            $extQty = (float) ($bestMatch['cantidad'] ?? 0);
            $extPrice = (float) ($bestMatch['precio_unitario'] ?? 0);

            $qtyDiff = $realQty > 0 ? abs($extQty - $realQty) / $realQty * 100 : ($extQty == 0 ? 0 : 100);
            $priceDiff = $realPrice > 0 ? abs($extPrice - $realPrice) / $realPrice * 100 : ($extPrice == 0 ? 0 : 100);

            $itemCorrect = $bestScore >= 80 && $qtyDiff < 10 && $priceDiff < 5;

            if ($itemCorrect) {
                $matched++;
            }

            $details[] = [
                'real' => $realItem,
                'extracted' => $bestMatch,
                'correct' => $itemCorrect,
                'name_similarity' => round($bestScore, 2),
                'qty_diff_pct' => round($qtyDiff, 2),
                'price_diff_pct' => round($priceDiff, 2),
            ];
        }

        $precision = count($realItems) > 0 ? $matched / count($realItems) : 1.0;

        return [
            'correct' => $precision >= 0.8,
            'precision' => round($precision, 2),
            'matched' => $matched,
            'total' => count($realItems),
            'details' => $details,
        ];
    }

    /**
     * Generate a quality report from ai_training_dataset.
     *
     * @return array Report with total processed, global precision, per-field precision, failed list
     */
    public function generarReporte(): array
    {
        $records = AiTrainingDataset::whereNotNull('precision_scores')
            ->whereNotNull('overall_precision')
            ->get();

        if ($records->isEmpty()) {
            return [
                'total_processed' => 0,
                'global_precision' => 0,
                'per_field' => [],
                'failed' => [],
                'alert' => false,
            ];
        }

        $totalProcessed = $records->count();
        $globalPrecision = round($records->avg('overall_precision'), 2);

        // Per-field precision
        $fieldTotals = ['proveedor' => 0, 'monto_total' => 0, 'items' => 0];
        $fieldCounts = ['proveedor' => 0, 'monto_total' => 0, 'items' => 0];

        $failed = [];

        foreach ($records as $record) {
            $scores = $record->precision_scores ?? [];
            foreach (['proveedor', 'monto_total', 'items'] as $field) {
                if (isset($scores[$field])) {
                    $fieldTotals[$field] += ($scores[$field]['correct'] ?? false) ? 1 : 0;
                    $fieldCounts[$field]++;
                }
            }

            if (($record->overall_precision ?? 0) < 70) {
                $failed[] = [
                    'id' => $record->id,
                    'compra_id' => $record->compra_id,
                    'overall_precision' => $record->overall_precision,
                    'precision_scores' => $record->precision_scores,
                ];
            }
        }

        $perField = [];
        foreach ($fieldTotals as $field => $total) {
            $count = $fieldCounts[$field];
            $perField[$field] = $count > 0 ? round(($total / $count) * 100, 2) : 0;
        }

        $alert = $globalPrecision < 70;
        if ($alert) {
            Log::warning("[ValidacionService] Precisión global ({$globalPrecision}%) por debajo del umbral 70%");
        }

        return [
            'total_processed' => $totalProcessed,
            'global_precision' => $globalPrecision,
            'per_field' => $perField,
            'failed' => $failed,
            'alert' => $alert,
        ];
    }
}
