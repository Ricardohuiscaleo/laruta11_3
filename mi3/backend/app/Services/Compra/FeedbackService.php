<?php

declare(strict_types=1);

namespace App\Services\Compra;

use App\Models\AiExtractionLog;
use App\Models\ExtractionFeedback;
use Illuminate\Support\Facades\Log;

class FeedbackService
{
    // ─── Public Methods ───

    /**
     * Captura feedback comparando datos extraídos vs datos guardados por el usuario.
     * Inserta un registro en extraction_feedback por cada campo diferente.
     *
     * Validates: Requirements 6.1, 6.2
     */
    public function capturarFeedback(int $extractionLogId, ?int $compraId, array $datosGuardados): void
    {
        $log = AiExtractionLog::find($extractionLogId);

        if (!$log || !$log->extracted_data) {
            Log::warning('FeedbackService: No se encontró log o extracted_data vacío', [
                'extraction_log_id' => $extractionLogId,
            ]);
            return;
        }

        $extractedData = $log->extracted_data;
        $diffs = $this->computeDiff($extractedData, $datosGuardados);

        if (empty($diffs)) {
            return;
        }

        $proveedor = $datosGuardados['proveedor'] ?? $extractedData['proveedor'] ?? null;
        $tipoImagen = $datosGuardados['tipo_imagen'] ?? $extractedData['tipo_imagen'] ?? null;

        foreach ($diffs as $diff) {
            ExtractionFeedback::create([
                'extraction_log_id' => $extractionLogId,
                'compra_id' => $compraId,
                'proveedor' => $proveedor,
                'tipo_imagen' => $tipoImagen,
                'field_name' => $diff['field_name'],
                'original_value' => $diff['original_value'],
                'corrected_value' => $diff['corrected_value'],
            ]);
        }
    }

    /**
     * Obtiene las últimas N correcciones para un proveedor/tipo como few-shot examples.
     *
     * Validates: Requirements 6.3, 6.5
     */
    public function getFewShotExamples(?string $proveedor, ?string $tipoImagen, int $limit = 5): array
    {
        $query = ExtractionFeedback::query();

        if ($proveedor) {
            $query->where('proveedor', $proveedor);
        } elseif ($tipoImagen) {
            $query->where('tipo_imagen', $tipoImagen);
        } else {
            return [];
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Formatea correcciones como texto natural para inyectar en prompt.
     *
     * Validates: Requirements 6.4
     */
    public function formatearEjemplos(array $corrections): string
    {
        if (empty($corrections)) {
            return '';
        }

        $lines = [];

        foreach ($corrections as $correction) {
            $proveedor = $correction['proveedor'] ?? 'este proveedor';
            $campo = $correction['field_name'] ?? 'campo';
            $original = $correction['original_value'] ?? '';
            $corregido = $correction['corrected_value'] ?? '';

            $lines[] = "En extracciones anteriores de {$proveedor}, el usuario corrigió {$campo} de '{$original}' a '{$corregido}'";
        }

        return implode("\n", $lines);
    }

    /**
     * Calcula diff campo a campo entre datos extraídos y datos guardados.
     * Manejo especial para array de items (comparar por nombre de item).
     *
     * Validates: Requirements 6.1, 6.2
     */
    public function computeDiff(array $original, array $final): array
    {
        $diffs = [];

        // Campos escalares a comparar
        $scalarFields = ['proveedor', 'rut_proveedor', 'fecha', 'metodo_pago', 'tipo_compra', 'monto_neto', 'iva', 'monto_total'];

        foreach ($scalarFields as $field) {
            $originalValue = $original[$field] ?? null;
            $finalValue = $final[$field] ?? null;

            if ($this->valuesAreDifferent($originalValue, $finalValue)) {
                $diffs[] = [
                    'field_name' => $field,
                    'original_value' => $this->castToString($originalValue),
                    'corrected_value' => $this->castToString($finalValue),
                ];
            }
        }

        // Comparación especial de items por nombre
        $originalItems = $original['items'] ?? [];
        $finalItems = $final['items'] ?? [];

        $this->diffItems($originalItems, $finalItems, $diffs);

        return $diffs;
    }

    // ─── Private Methods ───

    /**
     * Compara items por nombre y detecta diferencias en sus campos.
     */
    private function diffItems(array $originalItems, array $finalItems, array &$diffs): void
    {
        // Indexar items finales por nombre para lookup
        $finalByName = [];
        foreach ($finalItems as $index => $item) {
            $name = mb_strtolower(trim($item['nombre'] ?? ''));
            $finalByName[$name] = ['item' => $item, 'index' => $index];
        }

        $itemFields = ['cantidad', 'unidad', 'precio_unitario', 'subtotal'];

        foreach ($originalItems as $origIndex => $origItem) {
            $origName = mb_strtolower(trim($origItem['nombre'] ?? ''));

            if (isset($finalByName[$origName])) {
                $finalItem = $finalByName[$origName]['item'];

                // Comparar nombre exacto (case-sensitive)
                if (($origItem['nombre'] ?? '') !== ($finalItem['nombre'] ?? '')) {
                    $diffs[] = [
                        'field_name' => "items.{$origIndex}.nombre",
                        'original_value' => $this->castToString($origItem['nombre'] ?? null),
                        'corrected_value' => $this->castToString($finalItem['nombre'] ?? null),
                    ];
                }

                // Comparar campos numéricos del item
                foreach ($itemFields as $field) {
                    $origVal = $origItem[$field] ?? null;
                    $finalVal = $finalItem[$field] ?? null;

                    if ($this->valuesAreDifferent($origVal, $finalVal)) {
                        $diffs[] = [
                            'field_name' => "items.{$origIndex}.{$field}",
                            'original_value' => $this->castToString($origVal),
                            'corrected_value' => $this->castToString($finalVal),
                        ];
                    }
                }

                unset($finalByName[$origName]);
            } else {
                // Item original no existe en final → fue eliminado
                $diffs[] = [
                    'field_name' => "items.{$origIndex}",
                    'original_value' => json_encode($origItem),
                    'corrected_value' => null,
                ];
            }
        }

        // Items nuevos que no estaban en original
        foreach ($finalByName as $entry) {
            $diffs[] = [
                'field_name' => "items.{$entry['index']}",
                'original_value' => null,
                'corrected_value' => json_encode($entry['item']),
            ];
        }
    }

    /**
     * Determina si dos valores son diferentes (maneja tipos mixtos).
     */
    private function valuesAreDifferent(mixed $a, mixed $b): bool
    {
        // Ambos null/vacíos → iguales
        if ($this->isEmptyValue($a) && $this->isEmptyValue($b)) {
            return false;
        }

        // Uno vacío y otro no → diferentes
        if ($this->isEmptyValue($a) !== $this->isEmptyValue($b)) {
            return true;
        }

        // Comparación numérica si ambos son numéricos
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a !== (float) $b;
        }

        return (string) $a !== (string) $b;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return $value === null || $value === '';
    }

    private function castToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
