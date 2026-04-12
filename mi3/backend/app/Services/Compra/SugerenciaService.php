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
     * Pre-selects matches with confidence >= 80%.
     * Returns array of matched items with scores.
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

            $bestMatch = null;
            $bestScore = 0;
            $bestType = null;

            // Match against ingredients
            foreach ($ingredients as $ingredient) {
                $candidateLower = mb_strtolower($ingredient->name);
                similar_text($itemName, $candidateLower, $percent);

                if ($percent > $bestScore) {
                    $bestScore = $percent;
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
                similar_text($itemName, $candidateLower, $percent);

                if ($percent > $bestScore) {
                    $bestScore = $percent;
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
                'pre_selected' => $bestScore >= 80,
            ];
        }

        return $results;
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
