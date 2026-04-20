<?php

namespace App\Services\Compra;

use App\Enums\IngredientCategory;
use App\Events\CompraRegistrada;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Ingredient;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class CompraService
{
    /**
     * Registro atómico de compra.
     *
     * Replicates the logic from caja3/api/compras/registrar_compra.php:
     * compras → compras_detalle (with stock snapshots) → stock update → capital_trabajo
     */
    public function registrar(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // 1. Crear registro en compras
            $compra = Compra::create([
                'fecha_compra' => $data['fecha_compra'],
                'proveedor'    => $data['proveedor'],
                'tipo_compra'  => $data['tipo_compra'],
                'monto_total'  => $data['monto_total'],
                'metodo_pago'  => $data['metodo_pago'],
                'estado'       => 'pagado',
                'notas'        => $data['notas'] ?? null,
                'usuario'      => $data['usuario'] ?? 'Admin',
            ]);

            // 2. Insertar items con snapshot de inventario
            foreach ($data['items'] as $item) {
                $itemType = $item['item_type'] ?? 'ingredient';
                $stockAntes = null;
                $stockDespues = null;

                if ($itemType === 'ingredient' && !empty($item['ingrediente_id'])) {
                    $stockAntes = (float) DB::table('ingredients')
                        ->where('id', $item['ingrediente_id'])
                        ->value('current_stock');
                    $stockDespues = $stockAntes + (float) $item['cantidad'];
                } elseif ($itemType === 'product' && !empty($item['product_id'])) {
                    $stockAntes = (float) DB::table('products')
                        ->where('id', $item['product_id'])
                        ->value('stock_quantity');
                    $stockDespues = $stockAntes + (float) $item['cantidad'];
                }

                CompraDetalle::create([
                    'compra_id'       => $compra->id,
                    'ingrediente_id'  => !empty($item['ingrediente_id']) ? $item['ingrediente_id'] : null,
                    'product_id'      => !empty($item['product_id']) ? $item['product_id'] : null,
                    'item_type'       => $itemType,
                    'nombre_item'     => $item['nombre_item'],
                    'cantidad'        => $item['cantidad'],
                    'unidad'          => $item['unidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'subtotal'        => $item['subtotal'],
                    'stock_antes'     => $stockAntes,
                    'stock_despues'   => $stockDespues,
                ]);

                // 3. Actualizar inventario según tipo
                if ($itemType === 'ingredient' && !empty($item['ingrediente_id'])) {
                    $proveedor = $data['proveedor'] ?? '';
                    $update = [
                        'current_stock' => DB::raw('current_stock + ' . (float) $item['cantidad']),
                        'cost_per_unit' => $item['precio_unitario'],
                    ];
                    if ($proveedor !== '') {
                        $update['supplier'] = $proveedor;
                    }
                    DB::table('ingredients')
                        ->where('id', $item['ingrediente_id'])
                        ->update($update);

                    // Cascade: recalculate composite parents that use this ingredient
                    try {
                        app(\App\Services\Recipe\RecipeService::class)
                            ->cascadeCompositeCosts((int) $item['ingrediente_id']);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::warning('[CompraService] cascadeCompositeCosts failed: ' . $e->getMessage());
                    }
                } elseif ($itemType === 'product' && !empty($item['product_id'])) {
                    DB::table('products')
                        ->where('id', $item['product_id'])
                        ->update([
                            'stock_quantity' => DB::raw('stock_quantity + ' . (float) $item['cantidad']),
                        ]);
                }
            }

            // 4. Actualizar capital_trabajo del día (INSERT ON DUPLICATE KEY UPDATE)
            $fecha = date('Y-m-d', strtotime($data['fecha_compra']));
            DB::statement(
                "INSERT INTO capital_trabajo (fecha, egresos_compras, saldo_final)
                 VALUES (?, ?, 0)
                 ON DUPLICATE KEY UPDATE
                    egresos_compras = egresos_compras + VALUES(egresos_compras),
                    saldo_final = saldo_inicial + ingresos_ventas - (egresos_compras + VALUES(egresos_compras)) - egresos_gastos",
                [$fecha, $data['monto_total']]
            );

            // 5. Calcular saldo nuevo (same logic as caja3)
            $saldoNuevo = $this->calcularSaldo();

            // 6. Dispatch real-time event
            CompraRegistrada::dispatch(
                $compra->id,
                $compra->proveedor,
                (int) $compra->monto_total,
                count($data['items']),
            );

            \App\Events\StockActualizado::dispatch('compra');

            return [
                'compra_id'   => $compra->id,
                'saldo_nuevo' => $saldoNuevo,
            ];
        });
    }

    /**
     * Eliminar compra con rollback de stock.
     *
     * Replicates the logic from caja3/api/compras/delete_compra.php.
     */
    public function eliminar(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $compra = Compra::with('detalles')->findOrFail($id);

            // Revertir inventario por cada detalle
            foreach ($compra->detalles as $detalle) {
                $itemType = $detalle->item_type ?? 'ingredient';

                if ($itemType === 'ingredient' && $detalle->ingrediente_id) {
                    DB::table('ingredients')
                        ->where('id', $detalle->ingrediente_id)
                        ->update([
                            'current_stock' => DB::raw('current_stock - ' . (float) $detalle->cantidad),
                        ]);
                } elseif ($itemType === 'product' && $detalle->product_id) {
                    DB::table('products')
                        ->where('id', $detalle->product_id)
                        ->update([
                            'stock_quantity' => DB::raw('stock_quantity - ' . (float) $detalle->cantidad),
                        ]);
                }
            }

            // Eliminar detalles y compra
            DB::table('compras_detalle')->where('compra_id', $id)->delete();
            DB::table('compras')->where('id', $id)->delete();

            return ['success' => true, 'message' => 'Compra eliminada correctamente'];
        });
    }

    /**
     * Búsqueda fuzzy de ingredientes y productos activos.
     *
     * Replicates the complex query from caja3/api/compras/get_items_compra.php.
     */
    public function buscarItems(string $query): array
    {
        $likeQuery = '%' . $query . '%';

        // Ingredientes activos con última compra y vendido desde compra
        $ingredientes = DB::select("
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
            AND i.name LIKE ?
            ORDER BY i.name ASC
        ", [$likeQuery]);

        // Productos activos con última compra y vendido desde compra
        $productos = DB::select("
            SELECT
                p.id,
                p.name,
                c.name as category,
                'unidad' as unit,
                p.stock_quantity as current_stock,
                p.min_stock_level,
                'product' as type,
                p.category_id,
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
            AND p.name LIKE ?
            ORDER BY p.name ASC
        ", [$likeQuery]);

        return array_merge(
            array_map(fn ($row) => (array) $row, $ingredientes),
            array_map(fn ($row) => (array) $row, $productos)
        );
    }

    /**
     * Autocompletado de proveedores distintos desde tabla compras.
     *
     * Replicates caja3/api/compras/get_proveedores.php.
     */
    public function getProveedores(?string $query = null): array
    {
        $q = DB::table('compras')
            ->whereNotNull('proveedor')
            ->where('proveedor', '!=', '')
            ->distinct()
            ->orderBy('proveedor');

        if ($query) {
            $q->where('proveedor', 'LIKE', '%' . $query . '%');
        }

        return $q->pluck('proveedor')->toArray();
    }

    /**
     * Crear nuevo ingrediente.
     */
    public function crearIngrediente(array $data): Ingredient
    {
        $category = $data['category'] ?? null;
        if ($category !== null && !IngredientCategory::isValid($category)) {
            $category = null;
        }

        return Ingredient::create([
            'name'            => $data['name'],
            'category'        => $category,
            'unit'            => $data['unit'] ?? 'unidad',
            'cost_per_unit'   => $data['cost_per_unit'] ?? 0,
            'current_stock'   => 0,
            'min_stock_level' => $data['min_stock_level'] ?? 0,
            'supplier'        => $data['supplier'] ?? null,
            'is_active'       => 1,
        ]);
    }

    /**
     * Calcular saldo disponible.
     *
     * Replicates caja3/api/compras/get_saldo_disponible.php.
     */
    public function getSaldoDisponible(): array
    {
        // Ventas mes anterior
        $ventasAnterior = (float) DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->whereRaw('MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)')
            ->whereRaw('YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)')
            ->selectRaw('SUM(installment_amount - COALESCE(delivery_fee, 0)) as total')
            ->value('total') ?? 0;

        // Hardcoded injection for October 2025 (same as caja3)
        $lastMonth = date('Y-m', strtotime('-1 month'));
        if ($lastMonth === '2025-10') {
            $ventasAnterior += 695433;
        }

        // Ventas mes actual
        $ventasActual = (float) DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->whereRaw('MONTH(created_at) = MONTH(CURRENT_DATE())')
            ->whereRaw('YEAR(created_at) = YEAR(CURRENT_DATE())')
            ->selectRaw('SUM(installment_amount - COALESCE(delivery_fee, 0)) as total')
            ->value('total') ?? 0;

        $sueldos = 1590000;

        // Compras mes actual
        $comprasMes = (float) DB::table('compras')
            ->whereRaw('MONTH(fecha_compra) = MONTH(CURRENT_DATE())')
            ->whereRaw('YEAR(fecha_compra) = YEAR(CURRENT_DATE())')
            ->sum('monto_total');

        $saldo = $ventasAnterior + $ventasActual - $sueldos - $comprasMes;

        return [
            'saldo_disponible'    => $saldo,
            'ventas_mes_anterior' => $ventasAnterior,
            'ventas_mes_actual'   => $ventasActual,
            'sueldos'             => $sueldos,
            'compras_mes'         => $comprasMes,
        ];
    }

    /**
     * Return capital_trabajo records ordered by fecha DESC.
     *
     * Each record includes: fecha, saldo_inicial, ingresos_ventas,
     * egresos_compras, egresos_gastos, saldo_final.
     */
    public function getHistorialSaldo(): array
    {
        return DB::table('capital_trabajo')
            ->orderBy('fecha', 'desc')
            ->select([
                'fecha',
                'saldo_inicial',
                'ingresos_ventas',
                'egresos_compras',
                'egresos_gastos',
                'saldo_final',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Internal helper to calculate current saldo.
     */
    private function calcularSaldo(): float
    {
        $data = $this->getSaldoDisponible();
        return $data['saldo_disponible'];
    }
}
