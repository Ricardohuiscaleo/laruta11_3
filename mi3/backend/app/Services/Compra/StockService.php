<?php

namespace App\Services\Compra;

use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Get inventory with traffic light classification.
     *
     * Replicates the semáforo logic from the design:
     * - rojo: stock < min * 0.25
     * - amarillo: stock >= min * 0.25 AND stock <= min
     * - verde: stock > min
     */
    public function getInventario(string $tipo = 'ingredientes'): array
    {
        if ($tipo === 'bebidas') {
            return $this->getInventarioBebidas();
        }

        return $this->getInventarioIngredientes();
    }

    /**
     * Parse markdown text for bulk stock adjustment.
     *
     * Replicates caja3/api/ajuste_inventario.php parsing logic.
     * Pattern: `- {nombre}: {cantidad} {unidad}`
     */
    public function parsearMarkdown(string $texto): array
    {
        $allIngredients = DB::table('ingredients')
            ->where('is_active', 1)
            ->select('id', 'name', 'current_stock', 'unit')
            ->orderBy('name')
            ->get()
            ->toArray();

        $allProducts = DB::table('products')
            ->where('is_active', 1)
            ->select('id', 'name', 'stock_quantity as current_stock')
            ->orderBy('name')
            ->get()
            ->toArray();

        $valid = [];
        $errors = [];
        $lineNum = 0;

        foreach (explode("\n", $texto) as $line) {
            $trimmed = trim($line);
            $lineNum++;

            // Skip titles and empty lines
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Parse pattern: - {nombre}: {cantidad} {unidad}
            if (!preg_match('/^-\s+(.+?):\s*([\d.]+)\s*(\w+)?$/', $trimmed, $matches)) {
                $errors[] = [
                    'line'  => $lineNum,
                    'text'  => $trimmed,
                    'error' => 'Formato inválido. Usar: - nombre: cantidad unidad',
                ];
                continue;
            }

            $nombre = trim($matches[1]);
            $cantidad = (float) $matches[2];
            $unidad = trim($matches[3] ?? '');

            // Fuzzy match against ingredients first, then products
            $match = $this->findBestMatch($nombre, $allIngredients, $allProducts);

            if ($match) {
                $valid[] = [
                    'input_name'   => $nombre,
                    'matched_name' => $match['name'],
                    'matched_id'   => $match['id'],
                    'item_type'    => $match['item_type'],
                    'score'        => $match['score'],
                    'old_stock'    => (float) $match['current_stock'],
                    'new_stock'    => $cantidad,
                    'unit'         => $unidad ?: ($match['unit'] ?? 'unidad'),
                ];
            } else {
                $errors[] = [
                    'line'  => $lineNum,
                    'text'  => $trimmed,
                    'error' => "No se encontró coincidencia para '{$nombre}'",
                ];
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Apply bulk stock adjustment atomically.
     */
    public function aplicarAjuste(array $items): array
    {
        return DB::transaction(function () use ($items) {
            $applied = 0;

            foreach ($items as $item) {
                $itemType = $item['item_type'] ?? 'ingredient';

                if ($itemType === 'ingredient') {
                    DB::table('ingredients')
                        ->where('id', $item['matched_id'])
                        ->update([
                            'current_stock' => $item['new_stock'],
                            'updated_at'    => now(),
                        ]);
                } elseif ($itemType === 'product') {
                    DB::table('products')
                        ->where('id', $item['matched_id'])
                        ->update([
                            'stock_quantity' => $item['new_stock'],
                            'updated_at'    => now(),
                        ]);
                }

                $applied++;
            }

            return ['success' => true, 'applied' => $applied];
        });
    }

    /**
     * Generate beverages report with stock, sales, and status.
     *
     * Replicates caja3/api/compras/get_reporte_bebidas.php.
     */
    public function reporteBebidas(): string
    {
        $productos = DB::select("
            SELECT p.id, p.name, p.stock_quantity, p.min_stock_level, p.price, s.name as subcategory
            FROM products p
            LEFT JOIN subcategories s ON p.subcategory_id = s.id
            WHERE p.category_id = 5
              AND p.subcategory_id = 11
              AND p.is_active = 1
              AND p.name NOT LIKE '%Agua%'
            ORDER BY p.stock_quantity ASC, p.name ASC
        ");

        $target = 12;
        $fecha = date('d/m/Y');

        $criticos = [];
        $comprar = [];

        $md = "📦 *REPORTE BEBIDAS — {$fecha}*\n";
        $md .= "_(objetivo: {$target} unidades por producto)_\n\n";

        foreach ($productos as $p) {
            $stock = (int) $p->stock_quantity;
            $sugerido = max(0, $target - $stock);
            $emoji = $stock <= 2 ? '🔴' : ($stock <= 5 ? '🟡' : '🟢');

            if ($sugerido > 0) {
                $md .= "{$emoji} {$p->name} — stock: {$stock} → comprar: *{$sugerido}*\n";
            } else {
                $md .= "{$emoji} {$p->name} — stock: {$stock} ✓\n";
            }

            if ($stock <= 2) {
                $criticos[] = $p->name;
            }
            if ($sugerido > 0) {
                $comprar[] = [
                    'nombre'   => $p->name,
                    'cantidad' => $sugerido,
                    'precio'   => (float) $p->price,
                ];
            }
        }

        if (!empty($comprar)) {
            $total = 0;
            $md .= "─────────────────\n";
            $md .= "🛒 *COMPRA SUGERIDA*\n";
            foreach ($comprar as $c) {
                $sub = $c['cantidad'] * $c['precio'];
                $total += $sub;
                $md .= "• {$c['nombre']}: *{$c['cantidad']} u* — \$" . number_format($sub, 0, ',', '.') . "\n";
            }
            $md .= "\n*Total estimado: \$" . number_format($total, 0, ',', '.') . "*\n";
        }

        if (!empty($criticos)) {
            $md .= "\n⚠️ *CRÍTICOS:* " . implode(', ', $criticos) . "\n";
        }

        return $md;
    }

    /**
     * Get ingredients inventory with semáforo and last purchase data.
     */
    private function getInventarioIngredientes(): array
    {
        $items = DB::select("
            SELECT
                i.id,
                i.name,
                i.category,
                i.unit,
                i.current_stock,
                i.min_stock_level,
                i.cost_per_unit,
                i.supplier,
                'ingredient' as type,
                last_c.ultima_compra_cantidad,
                last_c.stock_despues_compra,
                last_c.fecha_ultima_compra,
                COALESCE((
                    SELECT
                        SUM(CASE WHEN it.transaction_type = 'sale' AND it.previous_stock >= 0 THEN ABS(it.quantity) ELSE 0 END)
                        - SUM(CASE WHEN it.transaction_type = 'return' THEN ABS(it.quantity) ELSE 0 END)
                    FROM inventory_transactions it
                    WHERE it.ingredient_id = i.id
                    AND it.transaction_type IN ('sale', 'return')
                    AND last_c.fecha_ultima_compra IS NOT NULL
                    AND it.created_at >= last_c.fecha_ultima_compra
                ), 0) as vendido_desde_compra
            FROM ingredients i
            LEFT JOIN (
                SELECT cd.ingrediente_id,
                    cd.cantidad as ultima_compra_cantidad,
                    cd.stock_despues as stock_despues_compra,
                    c.fecha_compra as fecha_ultima_compra
                FROM compras_detalle cd
                JOIN compras c ON cd.compra_id = c.id
                WHERE cd.id = (
                    SELECT cd2.id FROM compras_detalle cd2
                    JOIN compras c2 ON cd2.compra_id = c2.id
                    WHERE cd2.ingrediente_id = cd.ingrediente_id
                    ORDER BY c2.fecha_compra DESC, cd2.id DESC
                    LIMIT 1
                )
            ) last_c ON last_c.ingrediente_id = i.id
            WHERE i.is_active = 1
            ORDER BY i.name ASC
        ");

        return array_map(fn ($item) => $this->addSemaforo((array) $item), $items);
    }

    /**
     * Get beverages inventory with semáforo.
     * Bebidas: subcategory_id in [10, 11, 27, 28]
     */
    private function getInventarioBebidas(): array
    {
        $items = DB::select("
            SELECT
                p.id,
                p.name,
                c.name as category,
                s.name as subcategory,
                'unidad' as unit,
                p.stock_quantity as current_stock,
                p.min_stock_level,
                p.price,
                'product' as type,
                p.subcategory_id,
                last_c.ultima_compra_cantidad,
                last_c.stock_despues_compra,
                last_c.fecha_ultima_compra,
                COALESCE((
                    SELECT
                        SUM(CASE WHEN it.transaction_type = 'sale' AND it.previous_stock >= 0 THEN ABS(it.quantity) ELSE 0 END)
                        - SUM(CASE WHEN it.transaction_type = 'return' THEN ABS(it.quantity) ELSE 0 END)
                    FROM inventory_transactions it
                    WHERE it.product_id = p.id
                    AND it.transaction_type IN ('sale', 'return')
                    AND last_c.fecha_ultima_compra IS NOT NULL
                    AND it.created_at >= last_c.fecha_ultima_compra
                ), 0) as vendido_desde_compra
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN subcategories s ON p.subcategory_id = s.id
            LEFT JOIN (
                SELECT cd.product_id,
                    cd.cantidad as ultima_compra_cantidad,
                    cd.stock_despues as stock_despues_compra,
                    c.fecha_compra as fecha_ultima_compra
                FROM compras_detalle cd
                JOIN compras c ON cd.compra_id = c.id
                WHERE cd.id = (
                    SELECT cd2.id FROM compras_detalle cd2
                    JOIN compras c2 ON cd2.compra_id = c2.id
                    WHERE cd2.product_id = cd.product_id
                    ORDER BY c2.fecha_compra DESC, cd2.id DESC
                    LIMIT 1
                )
            ) last_c ON last_c.product_id = p.id
            WHERE p.is_active = 1
            AND p.subcategory_id IN (10, 11, 27, 28)
            ORDER BY p.name ASC
        ");

        return array_map(fn ($item) => $this->addSemaforo((array) $item), $items);
    }

    /**
     * Add semáforo classification to an item.
     *
     * - rojo: stock < min * 0.25
     * - amarillo: stock >= min * 0.25 AND stock <= min
     * - verde: stock > min
     */
    private function addSemaforo(array $item): array
    {
        $stock = (float) ($item['current_stock'] ?? 0);
        $min = (float) ($item['min_stock_level'] ?? 0);

        if ($min <= 0) {
            $item['semaforo'] = 'verde';
        } elseif ($stock < $min * 0.25) {
            $item['semaforo'] = 'rojo';
        } elseif ($stock <= $min) {
            $item['semaforo'] = 'amarillo';
        } else {
            $item['semaforo'] = 'verde';
        }

        return $item;
    }

    /**
     * Fuzzy match a name against ingredients and products.
     *
     * Uses similar_text with a threshold of 60% (same as caja3).
     */
    private function findBestMatch(string $name, array $ingredients, array $products): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ($ingredients as $ing) {
            $ingObj = (array) $ing;
            similar_text(strtolower($name), strtolower($ingObj['name']), $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = array_merge($ingObj, ['item_type' => 'ingredient']);
            }
        }

        foreach ($products as $prod) {
            $prodObj = (array) $prod;
            similar_text(strtolower($name), strtolower($prodObj['name']), $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = array_merge($prodObj, ['item_type' => 'product']);
            }
        }

        if ($bestScore >= 60 && $best !== null) {
            $best['score'] = round($bestScore);
            return $best;
        }

        return null;
    }
}
