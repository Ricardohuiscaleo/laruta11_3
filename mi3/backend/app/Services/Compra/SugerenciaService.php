<?php

namespace App\Services\Compra;

use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\ExtractionFeedback;
use App\Models\Ingredient;
use App\Models\Product;
use App\Models\SupplierIndex;
use Illuminate\Support\Facades\DB;

class SugerenciaService
{
    /**
     * Fuzzy match a supplier name against known suppliers in supplier_index.
     *
     * Uses similar_text() with a 60% threshold.
     * Returns the best match with score, or null if no match above threshold.
     */
    public function matchProveedor(string $nombre): ?array
    {
        $suppliers = SupplierIndex::all();

        if ($suppliers->isEmpty()) {
            return null;
        }

        $bestMatch = null;
        $bestScore = 0;

        $nombreLower = mb_strtolower(trim($nombre));

        foreach ($suppliers as $supplier) {
            $candidateLower = mb_strtolower($supplier->nombre_normalizado);
            similar_text($nombreLower, $candidateLower, $percent);

            if ($percent > $bestScore) {
                $bestScore = $percent;
                $bestMatch = $supplier;
            }
        }

        if ($bestScore < 60) {
            return null;
        }

        return [
            'id' => $bestMatch->id,
            'nombre_normalizado' => $bestMatch->nombre_normalizado,
            'nombre_original' => $bestMatch->nombre_original,
            'rut' => $bestMatch->rut,
            'frecuencia' => $bestMatch->frecuencia,
            'items_habituales' => $bestMatch->items_habituales,
            'ultimo_precio_por_item' => $bestMatch->ultimo_precio_por_item,
            'score' => round($bestScore, 2),
        ];
    }

    /**
     * Fuzzy match extracted items against ingredients and products.
     *
     * Uses multi-strategy matching:
     * 1. Keyword-based matching (split name into words, match against DB names)
     * 2. similar_text() as fallback
     * Pre-selects matches with confidence >= 75%.
     */
    public function matchItems(array $items): array
    {
        $ingredients = Ingredient::where('is_active', 1)->get(['id', 'name', 'unit', 'cost_per_unit', 'current_stock']);
        $products = Product::where('is_active', 1)->get(['id', 'name', 'stock_quantity', 'price']);

        $results = [];

        foreach ($items as $item) {
            $itemName = mb_strtolower(trim($item['nombre'] ?? ''));
            if ($itemName === '') {
                $results[] = [
                    'original' => $item,
                    'match' => null,
                    'score' => 0,
                    'pre_selected' => false,
                ];
                continue;
            }

            // Extract meaningful keywords (ignore packaging noise like "800g", "10u", etc.)
            $keywords = $this->extractKeywords($itemName);

            $bestMatch = null;
            $bestScore = 0;
            $bestType = null;

            // Match against ingredients
            foreach ($ingredients as $ingredient) {
                $candidateLower = mb_strtolower($ingredient->name);
                $score = $this->smartScore($itemName, $keywords, $candidateLower);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'id' => $ingredient->id,
                        'name' => $ingredient->name,
                        'unit' => $ingredient->unit,
                        'cost_per_unit' => $ingredient->cost_per_unit,
                        'current_stock' => $ingredient->current_stock,
                    ];
                    $bestType = 'ingredient';
                }
            }

            // Match against products
            foreach ($products as $product) {
                $candidateLower = mb_strtolower($product->name);
                $score = $this->smartScore($itemName, $keywords, $candidateLower);

                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [
                        'id' => $product->id,
                        'name' => $product->name,
                        'unit' => 'unidad',
                        'stock_quantity' => $product->stock_quantity,
                        'price' => $product->price,
                    ];
                    $bestType = 'product';
                }
            }

            $results[] = [
                'original' => $item,
                'match' => $bestMatch,
                'match_type' => $bestType,
                'score' => round($bestScore, 2),
                'pre_selected' => $bestScore >= 75,
            ];
        }

        return $results;
    }

    /**
     * Extract meaningful keywords from a product name, ignoring packaging notation.
     */
    private function extractKeywords(string $name): array
    {
        // Remove packaging patterns: 800g, 10u, 8x1, 5kg, etc.
        $cleaned = preg_replace('/\b\d+\s*(g|kg|u|ml|l|cc|x\d+)\b/i', '', $name);
        // Remove standalone numbers
        $cleaned = preg_replace('/\b\d+\b/', '', $cleaned);
        // Normalize accents for better matching
        $cleaned = $this->removeAccents($cleaned);
        // Split into words and filter
        $words = preg_split('/[\s\-_\/]+/', $cleaned);
        return array_values(array_filter($words, fn($w) => mb_strlen($w) >= 2));
    }

    /**
     * Remove accents/diacritics for fuzzy matching.
     */
    private function removeAccents(string $str): string
    {
        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N', 'ü' => 'u', 'Ü' => 'U',
        ];
        return strtr($str, $map);
    }

    /**
     * Smart scoring: combines keyword matching with similar_text.
     * Keyword matching is more robust for invoice product names.
     */
    private function smartScore(string $itemName, array $keywords, string $candidate): float
    {
        // Normalize accents in candidate for matching
        $candidateNorm = $this->removeAccents($candidate);
        $candidateWords = preg_split('/[\s\-_\/]+/', $candidateNorm);
        $candidateWords = array_values(array_filter($candidateWords, fn($w) => mb_strlen($w) >= 2));

        $keywordScore = 0;
        if (!empty($keywords) && !empty($candidateWords)) {
            // Score A: what % of OCR keywords match the DB candidate
            $matchedFromOcr = 0;
            foreach ($keywords as $kw) {
                foreach ($candidateWords as $cw) {
                    if (str_starts_with($kw, $cw) || str_starts_with($cw, $kw)) {
                        $matchedFromOcr++;
                        break;
                    }
                    similar_text($kw, $cw, $wordPct);
                    if ($wordPct >= 80) {
                        $matchedFromOcr++;
                        break;
                    }
                }
            }
            $scoreA = (count($keywords) > 0) ? ($matchedFromOcr / count($keywords)) * 100 : 0;

            // Score B: what % of DB candidate words are found in OCR keywords
            // This handles "salchicha" (1 word) matching "salchicha big mont" (3 words)
            $matchedFromDb = 0;
            foreach ($candidateWords as $cw) {
                foreach ($keywords as $kw) {
                    if (str_starts_with($kw, $cw) || str_starts_with($cw, $kw)) {
                        $matchedFromDb++;
                        break;
                    }
                    similar_text($kw, $cw, $wordPct);
                    if ($wordPct >= 80) {
                        $matchedFromDb++;
                        break;
                    }
                }
            }
            $scoreB = (count($candidateWords) > 0) ? ($matchedFromDb / count($candidateWords)) * 100 : 0;

            $keywordScore = max($scoreA, $scoreB);
        }

        // Strategy 2: Full string similar_text
        similar_text($itemName, $candidate, $fullPct);

        // Return the best of both strategies
        return max($keywordScore, $fullPct);
    }

    /**
     * Update supplier_index after a purchase is registered.
     *
     * If supplier exists (by nombre_normalizado): increment frecuencia,
     * update ultima_compra, merge items_habituales and ultimo_precio_por_item.
     * If new supplier: create new record with frecuencia=1.
     */
    public function actualizarIndice(Compra $compra): void
    {
        $proveedor = trim($compra->proveedor ?? '');
        if ($proveedor === '') {
            return;
        }

        $nombreNormalizado = mb_strtolower($proveedor);
        $fechaCompra = $compra->fecha_compra ? $compra->fecha_compra->format('Y-m-d') : date('Y-m-d');

        // Build items data from compra detalles
        $detalles = $compra->detalles()->get();
        $itemsFromCompra = [];
        $preciosPorItem = [];

        foreach ($detalles as $detalle) {
            $itemName = mb_strtolower(trim($detalle->nombre_item));
            if ($itemName === '') {
                continue;
            }

            $preciosPorItem[$itemName] = (float) $detalle->precio_unitario;

            $itemsFromCompra[$itemName] = [
                'nombre' => $detalle->nombre_item,
                'frecuencia' => 1,
                'precio_promedio' => (float) $detalle->precio_unitario,
            ];
        }

        $existing = SupplierIndex::where('nombre_normalizado', $nombreNormalizado)->first();

        if ($existing) {
            // Merge items_habituales
            $habituales = $existing->items_habituales ?? [];
            $habitualesMap = [];
            foreach ($habituales as $h) {
                $key = mb_strtolower($h['nombre'] ?? '');
                $habitualesMap[$key] = $h;
            }

            foreach ($itemsFromCompra as $key => $newItem) {
                if (isset($habitualesMap[$key])) {
                    $old = $habitualesMap[$key];
                    $oldFreq = $old['frecuencia'] ?? 1;
                    $oldAvg = $old['precio_promedio'] ?? 0;
                    $newFreq = $oldFreq + 1;
                    $newAvg = (($oldAvg * $oldFreq) + $newItem['precio_promedio']) / $newFreq;
                    $habitualesMap[$key] = [
                        'nombre' => $old['nombre'],
                        'frecuencia' => $newFreq,
                        'precio_promedio' => round($newAvg, 2),
                    ];
                } else {
                    $habitualesMap[$key] = $newItem;
                }
            }

            // Merge ultimo_precio_por_item
            $ultimoPrecios = $existing->ultimo_precio_por_item ?? [];
            foreach ($preciosPorItem as $itemName => $precio) {
                $ultimoPrecios[$itemName] = $precio;
            }

            $existing->update([
                'frecuencia' => $existing->frecuencia + 1,
                'ultima_compra' => $fechaCompra,
                'items_habituales' => array_values($habitualesMap),
                'ultimo_precio_por_item' => $ultimoPrecios,
            ]);
        } else {
            SupplierIndex::create([
                'nombre_normalizado' => $nombreNormalizado,
                'nombre_original' => $proveedor,
                'frecuencia' => 1,
                'items_habituales' => array_values($itemsFromCompra),
                'ultimo_precio_por_item' => $preciosPorItem,
                'primera_compra' => $fechaCompra,
                'ultima_compra' => $fechaCompra,
            ]);
        }
    }

    /**
     * Save user corrections to extraction_feedback table.
     *
     * Each correction has field_name, original_value, corrected_value.
     */
    public function registrarFeedback(int $extractionLogId, int $compraId, array $correcciones): void
    {
        foreach ($correcciones as $correccion) {
            ExtractionFeedback::create([
                'extraction_log_id' => $extractionLogId,
                'compra_id' => $compraId,
                'field_name' => $correccion['field_name'],
                'original_value' => $correccion['original_value'],
                'corrected_value' => $correccion['corrected_value'],
            ]);
        }
    }

    /**
     * Return last purchase price for an item from compras_detalle JOIN compras.
     *
     * Replicates caja3/api/compras/get_precio_historico.php.
     */
    public function precioHistorico(int $itemId, string $itemType = 'ingredient'): ?array
    {
        $column = $itemType === 'product' ? 'product_id' : 'ingrediente_id';

        $ultimo = DB::table('compras_detalle as cd')
            ->join('compras as c', 'cd.compra_id', '=', 'c.id')
            ->where("cd.{$column}", $itemId)
            ->orderBy('c.fecha_compra', 'desc')
            ->orderBy('cd.id', 'desc')
            ->select([
                'cd.precio_unitario',
                'cd.cantidad',
                'cd.unidad',
                'cd.subtotal',
                'c.fecha_compra',
                'c.proveedor',
            ])
            ->first();

        if (!$ultimo) {
            return null;
        }

        return [
            'precio_unitario' => $ultimo->precio_unitario,
            'unidad' => $ultimo->unidad,
            'ultima_cantidad' => $ultimo->cantidad,
            'ultimo_subtotal' => $ultimo->subtotal,
            'fecha_compra' => $ultimo->fecha_compra,
            'proveedor' => $ultimo->proveedor,
        ];
    }

    /**
     * Return the last registered price for pre-filling the form.
     */
    public function sugerirPrecio(int $itemId, string $itemType = 'ingredient'): ?float
    {
        $historico = $this->precioHistorico($itemId, $itemType);

        if (!$historico) {
            return null;
        }

        return (float) $historico['precio_unitario'];
    }
}
