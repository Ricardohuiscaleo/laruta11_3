<?php

declare(strict_types=1);

namespace App\Services\Ventas;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VentasService
{
    /**
     * Build the date range for a given period.
     *
     * shift_today = 17:30 Chile → 04:00 next day Chile
     *   We use Carbon with America/Santiago to handle DST automatically.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getDateRange(string $period): array
    {
        $now = Carbon::now('America/Santiago');

        return match ($period) {
            'shift_today' => $this->getShiftRange($now),
            'today'       => [
                $now->copy()->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            'week'        => [
                $now->copy()->startOfWeek(Carbon::MONDAY)->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            'month'       => [
                $now->copy()->startOfMonth()->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ],
            default       => $this->getShiftRange($now),
        };
    }

    /**
     * Shift logic: 17:30 of the day → 04:00 of the next day (Chile time).
     * If current time is before 04:00, the shift started yesterday at 17:30.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getShiftRange(Carbon $now): array
    {
        $hour = (int) $now->format('G');
        $minute = (int) $now->format('i');
        $currentMinutes = $hour * 60 + $minute;

        // Before 04:00 → shift started yesterday at 17:30
        if ($currentMinutes < 240) {
            $shiftDate = $now->copy()->subDay();
        } else {
            $shiftDate = $now->copy();
        }

        $start = $shiftDate->copy()->setTime(17, 30, 0)->utc();
        $end = $shiftDate->copy()->addDay()->setTime(4, 0, 0)->utc();

        return [$start, $end];
    }

    /**
     * Scope: exclude credit payment orders (RL6-*) from sales queries.
     * These are credit repayments, not product sales.
     */
    private function excludeCreditPayments(\Illuminate\Database\Query\Builder $query, string $column = 'order_number'): \Illuminate\Database\Query\Builder
    {
        return $query->where($column, 'NOT LIKE', 'RL6-%');
    }

    /**
     * Aggregated KPIs for the given period.
     * total_sales = installment_amount - delivery_fee (net sales)
     * total_cost = SUM(item_cost * quantity) from tuu_order_items
     *
     * @return array{total_sales: float, total_cost: float, total_profit: float, order_count: int, avg_ticket: float}
     */
    public function getKpis(string $period): array
    {
        [$start, $end] = $this->getDateRange($period);

        $row = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('
                COALESCE(SUM(installment_amount), 0) as gross_sales,
                COALESCE(SUM(COALESCE(delivery_fee, 0)), 0) as total_delivery,
                COUNT(*) as order_count
            ')
            ->first();

        $grossSales = (float) $row->gross_sales;
        $totalDelivery = (float) $row->total_delivery;
        $orderCount = (int) $row->order_count;
        $totalSales = $grossSales - $totalDelivery;

        // Cost from tuu_order_items joined with paid orders
        $totalCost = (float) DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as total_cost')
            ->value('total_cost');

        $totalProfit = $totalSales - $totalCost;
        $avgTicket = $orderCount > 0 ? round($totalSales / $orderCount) : 0;

        return [
            'total_sales'    => $totalSales,
            'total_delivery' => $totalDelivery,
            'total_cost'     => $totalCost,
            'total_profit'   => $totalProfit,
            'order_count'    => $orderCount,
            'avg_ticket'     => $avgTicket,
        ];
    }

    /**
     * Paginated transactions for the given period.
     *
     * @return array{data: array, total: int, page: int, per_page: int, last_page: int}
     */
    public function getTransactions(string $period, ?string $search, int $page = 1, int $perPage = 50): array
    {
        [$start, $end] = $this->getDateRange($period);

        $query = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$start, $end]);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('order_number', 'like', "%{$search}%");
            });
        }

        $total = $query->count();
        $lastPage = max(1, (int) ceil($total / $perPage));

        $rows = (clone $query)
            ->orderByDesc('created_at')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->select([
                'id',
                'order_number',
                'customer_name',
                'installment_amount as total',
                'delivery_fee',
                'payment_method',
                'product_name',
                'created_at',
            ])
            ->get()
            ->map(function ($row) {
                $row->total = (float) $row->total;
                $row->delivery_fee = (float) ($row->delivery_fee ?? 0);
                $row->net = $row->total - $row->delivery_fee;
                $row->source = $this->detectSource($row->order_number);

                return $row;
            });

        return [
            'data'      => $rows->toArray(),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /**
     * Payment breakdown grouped by payment_method.
     *
     * @return array<int, array{method: string, order_count: int, total_sales: float, total_cost: float, profit: float}>
     */
    public function getPaymentBreakdown(string $period): array
    {
        [$start, $end] = $this->getDateRange($period);

        $rows = DB::table('tuu_orders as o')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$start, $end])
            ->groupBy('o.payment_method')
            ->selectRaw('
                o.payment_method as method,
                COUNT(*) as order_count,
                SUM(o.installment_amount) - SUM(COALESCE(o.delivery_fee, 0)) as total_sales,
                COALESCE(SUM((
                    SELECT SUM(oi.item_cost * oi.quantity)
                    FROM tuu_order_items oi
                    WHERE oi.order_reference = o.order_number
                )), 0) as total_cost
            ')
            ->get();

        return $rows->map(function ($row) {
            $sales = (float) $row->total_sales;
            $cost = (float) $row->total_cost;

            return [
                'method'      => $row->method ?? 'otros',
                'order_count' => (int) $row->order_count,
                'total_sales' => $sales,
                'total_cost'  => $cost,
                'profit'      => $sales - $cost,
            ];
        })->toArray();
    }

    /**
     * Full detail for a single order: items, ingredient consumption, totals.
     *
     * @return array|null  null when order doesn't exist or isn't paid
     */
    public function getOrderDetail(string $orderNumber): ?array
    {
        // Query 1 — find the paid order
        $order = DB::table('tuu_orders')
            ->where('order_number', $orderNumber)
            ->where('payment_status', 'paid')
            ->select([
                'order_number',
                'customer_name',
                'payment_method',
                'installment_amount',
                'delivery_fee',
                'dispatch_photo_url',
                'created_at',
            ])
            ->first();

        if (!$order) {
            return null;
        }

        // Query 2 — order items
        $items = DB::table('tuu_order_items')
            ->leftJoin('products', 'tuu_order_items.product_id', '=', 'products.id')
            ->where('order_reference', $orderNumber)
            ->select([
                'tuu_order_items.id',
                'tuu_order_items.product_id',
                'tuu_order_items.product_name',
                'tuu_order_items.product_price',
                'tuu_order_items.item_cost',
                'tuu_order_items.quantity',
                'products.image_url',
            ])
            ->get();

        // Query 3 — real ingredient consumption (only if table exists)
        $realConsumption = collect();
        $hasInventoryTable = $this->tableExists('inventory_transactions');

        if ($hasInventoryTable) {
            $realConsumption = DB::table('inventory_transactions as it')
                ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
                ->where('it.order_reference', $orderNumber)
                ->where('it.transaction_type', 'sale')
                ->select([
                    'it.order_item_id',
                    'it.product_id',
                    'it.ingredient_id',
                    'i.name as ingredient_name',
                    'i.unit',
                    'it.quantity as quantity_used',
                    'it.previous_stock',
                    'it.new_stock',
                    'i.min_stock_level',
                ])
                ->get();
        }

        // Group by order_item_id first (preferred), fallback to product_id
        $realByItemId = $realConsumption->groupBy('order_item_id');
        $realByProduct = $realConsumption->filter(fn ($r) => $r->product_id !== null)->groupBy('product_id');

        // Build items array with ingredients
        $resultItems = [];
        foreach ($items as $item) {
            $unitPrice = (float) $item->product_price;
            $itemCost  = (float) $item->item_cost;
            $quantity  = (int) $item->quantity;
            $profit    = $unitPrice - $itemCost;

            $ingredients = [];
            $itemId    = $item->id;
            $productId = $item->product_id;

            // Priority: match by order_item_id, then by product_id
            $matched = $realByItemId->get($itemId) ?? ($productId ? $realByProduct->get($productId) : null);

            if ($matched && $matched->isNotEmpty()) {
                foreach ($matched as $row) {
                    $prevStock     = $row->previous_stock !== null ? (float) $row->previous_stock : null;
                    $newStock      = $row->new_stock !== null ? (float) $row->new_stock : null;
                    $minStockLevel = (float) $row->min_stock_level;
                    $qtyUsed       = abs((float) $row->quantity_used);

                    $ingredients[] = [
                        'ingredient_name' => $row->ingredient_name,
                        'quantity_used'   => $qtyUsed,
                        'unit'            => $row->unit,
                        'stock_before'    => $prevStock,
                        'stock_after'     => $newStock,
                        'stock_status'    => ($newStock !== null && $newStock < $minStockLevel) ? 'warning' : 'ok',
                    ];
                }
            } elseif ($productId) {
                // Query 4 (fallback) — theoretical consumption from product_recipes
                $recipes = DB::table('product_recipes as pr')
                    ->join('ingredients as i', 'pr.ingredient_id', '=', 'i.id')
                    ->where('pr.product_id', $productId)
                    ->select([
                        'pr.ingredient_id',
                        'i.name as ingredient_name',
                        'pr.quantity',
                        'pr.unit',
                        'i.min_stock_level',
                        'i.current_stock',
                    ])
                    ->get();

                foreach ($recipes as $recipe) {
                    $quantityUsed  = (float) $recipe->quantity * $quantity;
                    $currentStock  = (float) $recipe->current_stock;
                    $minStockLevel = (float) $recipe->min_stock_level;

                    $ingredients[] = [
                        'ingredient_name' => $recipe->ingredient_name,
                        'quantity_used'   => $quantityUsed,
                        'unit'            => $recipe->unit,
                        'stock_before'    => null,
                        'stock_after'     => null,
                        'stock_status'    => $currentStock < $minStockLevel ? 'warning' : 'ok',
                    ];
                }
            }

            $resultItems[] = [
                'product_name' => $item->product_name,
                'image_url'    => $item->image_url ?? null,
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'item_cost'    => $itemCost,
                'profit'       => $profit,
                'ingredients'  => $ingredients,
            ];
        }

        // Calculate totals
        $subtotal    = 0;
        $totalCost   = 0;
        foreach ($resultItems as $ri) {
            $subtotal  += $ri['unit_price'] * $ri['quantity'];
            $totalCost += $ri['item_cost'] * $ri['quantity'];
        }
        $totalProfit = $subtotal - $totalCost;

        // Parse dispatch photos
        $dispatchPhotos = [];
        if (!empty($order->dispatch_photo_url)) {
            $decoded = json_decode($order->dispatch_photo_url, true);
            if (is_array($decoded)) {
                foreach ($decoded as $type => $data) {
                    if (is_string($data)) {
                        $dispatchPhotos[] = ['type' => $type, 'url' => $data];
                    } elseif (is_array($data) && isset($data['url'])) {
                        $dispatchPhotos[] = [
                            'type'         => $type,
                            'url'          => $data['url'],
                            'verification' => $data['verification'] ?? null,
                        ];
                    }
                }
            }
        }

        return [
            'order_number'    => $order->order_number,
            'created_at'      => $order->created_at,
            'customer_name'   => $order->customer_name,
            'payment_method'  => $order->payment_method,
            'dispatch_photos' => $dispatchPhotos,
            'items'           => $resultItems,
            'totals'         => [
                'subtotal'     => $subtotal,
                'delivery_fee' => (float) ($order->delivery_fee ?? 0),
                'total'        => $subtotal + (float) ($order->delivery_fee ?? 0),
                'total_cost'   => $totalCost,
                'total_profit' => $totalProfit,
            ],
        ];
    }

    /**
     * Check if a database table exists.
     */
    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    /**
     * Top products by quantity sold or profit for the given period.
     *
     * @return array<int, array{product_name: string, quantity_sold: int, total_revenue: float, total_cost: float, total_profit: float, margin_pct: float}>
     */
    public function getTopProducts(string $period, int $limit = 10, string $sort = 'quantity'): array
    {
        [$start, $end] = $this->getDateRange($period);

        $orderBy = $sort === 'profit' ? 'total_profit' : 'quantity_sold';

        return DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$start, $end])
            ->groupBy('oi.product_name')
            ->selectRaw("
                oi.product_name,
                SUM(oi.quantity) as quantity_sold,
                SUM(oi.product_price * oi.quantity) as total_revenue,
                SUM(oi.item_cost * oi.quantity) as total_cost,
                SUM((oi.product_price - oi.item_cost) * oi.quantity) as total_profit
            ")
            ->orderByDesc($orderBy)
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->total_revenue;
                $cost = (float) $row->total_cost;
                $profit = (float) $row->total_profit;

                return [
                    'product_name'  => $row->product_name,
                    'quantity_sold' => (int) $row->quantity_sold,
                    'total_revenue' => $revenue,
                    'total_cost'    => $cost,
                    'total_profit'  => $profit,
                    'margin_pct'    => $revenue > 0 ? round(($profit / $revenue) * 100, 1) : 0,
                ];
            })
            ->toArray();
    }

    /**
     * CMV breakdown by ingredient for the given period.
     *
     * @param string $period  Period key (shift_today, today, week, month)
     * @param string|null $month  Optional YYYY-MM to override period with a specific month
     * @return array{total_cmv: float, cmv_percentage: float, ingredients: array}
     */
    public function getCmvBreakdown(string $period, ?string $month = null): array
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $base = Carbon::createFromFormat('Y-m-d', $month . '-01', 'America/Santiago');
            $start = $base->copy()->startOfMonth()->startOfDay()->utc();
            $end = $base->copy()->endOfMonth()->endOfDay()->utc();
        } else {
            [$start, $end] = $this->getDateRange($period);
        }

        // Total sales for percentage calculation
        $totalSales = (float) DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(installment_amount) - SUM(COALESCE(delivery_fee, 0)), 0) as net')
            ->value('net');

        // Exclude children of composite ingredients that are NOT used directly in any product recipe
        // (e.g., Carne Molida is only a sub-recipe of Hamburguesa R11, so exclude it to avoid double counting)
        $exclusiveChildIds = [];
        if ($this->tableExists('ingredient_recipes')) {
            $exclusiveChildIds = DB::table('ingredient_recipes')
                ->whereNotIn('child_ingredient_id', function ($q) {
                    $q->select('ingredient_id')->from('product_recipes');
                })
                ->pluck('child_ingredient_id')
                ->unique()
                ->toArray();
        }

        // Total CMV from inventory_transactions (real ingredient consumption × current cost)
        // This is more accurate than item_cost which may have stale prices
        // MUST exclude the same composite children as the breakdown to avoid double counting
        $totalCmv = 0;
        if ($this->tableExists('inventory_transactions')) {
            $cmvQuery = DB::table('inventory_transactions as it')
                ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
                ->join('tuu_orders as o', 'it.order_reference', '=', 'o.order_number')
                ->where('it.transaction_type', 'sale')
                ->where('o.payment_status', 'paid')
                ->where('o.order_number', 'NOT LIKE', 'RL6-%')
                ->whereBetween('o.created_at', [$start, $end]);

            if (!empty($exclusiveChildIds)) {
                $cmvQuery->whereNotIn('it.ingredient_id', $exclusiveChildIds);
            }

            $totalCmv = (float) $cmvQuery
                ->selectRaw('COALESCE(SUM(ABS(it.quantity) * i.cost_per_unit), 0) as total')
                ->value('total');
        }

        // Ingredient breakdown from inventory_transactions
        $ingredients = [];
        $ingredientsCmvTotal = 0;
        if ($this->tableExists('inventory_transactions')) {
            $query = DB::table('inventory_transactions as it')
                ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
                ->join('tuu_orders as o', 'it.order_reference', '=', 'o.order_number')
                ->where('it.transaction_type', 'sale')
                ->where('o.payment_status', 'paid')
                ->where('o.order_number', 'NOT LIKE', 'RL6-%')
                ->whereBetween('o.created_at', [$start, $end]);

            if (!empty($exclusiveChildIds)) {
                $query->whereNotIn('it.ingredient_id', $exclusiveChildIds);
            }

            $ingredients = $query
                ->groupBy('it.ingredient_id', 'i.name', 'i.unit', 'i.cost_per_unit')
                ->selectRaw("
                    it.ingredient_id,
                    i.name,
                    i.unit,
                    SUM(ABS(it.quantity)) as total_quantity,
                    SUM(ABS(it.quantity) * i.cost_per_unit) as total_cost
                ")
                ->orderByDesc('total_cost')
                ->limit(50)
                ->get()
                ->map(function ($row) use ($totalCmv) {
                    $cost = (float) $row->total_cost;
                    return [
                        'ingredient_id'  => $row->ingredient_id,
                        'name'           => $row->name,
                        'total_quantity' => round((float) $row->total_quantity, 2),
                        'unit'           => $row->unit,
                        'total_cost'     => $cost,
                        'percentage'     => $totalCmv > 0 ? round(($cost / $totalCmv) * 100, 1) : 0,
                    ];
                })
                ->toArray();

            $ingredientsCmvTotal = array_sum(array_column($ingredients, 'total_cost'));
        }

        return [
            'total_cmv'      => $totalCmv,
            'cmv_percentage' => $totalSales > 0 ? round(($totalCmv / $totalSales) * 100, 1) : 0,
            'ingredients'    => $ingredients,
        ];
    }

    /**
     * Get breakdown of which products consumed a specific ingredient in a period.
     * Returns: [{product_name, times_sold, recipe_qty, total_consumed, unit, cost}]
     */
    public function getCmvIngredientProducts(int $ingredientId, string $period, ?string $month = null): array
    {
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $base = Carbon::createFromFormat('Y-m-d', $month . '-01', 'America/Santiago');
            $start = $base->copy()->startOfMonth()->startOfDay()->utc();
            $end = $base->copy()->endOfMonth()->endOfDay()->utc();
        } else {
            [$start, $end] = $this->getDateRange($period);
        }

        // Get all sale transactions for this ingredient, grouped by order_item
        $transactions = DB::table('inventory_transactions as it')
            ->join('tuu_orders as o', 'it.order_reference', '=', 'o.order_number')
            ->join('tuu_order_items as oi', function ($join) {
                $join->on('oi.order_reference', '=', 'it.order_reference')
                     ->where('oi.item_type', '=', 'product');
            })
            ->where('it.ingredient_id', $ingredientId)
            ->where('it.transaction_type', 'sale')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$start, $end])
            ->whereNotNull('oi.product_id')
            ->select('oi.product_id', 'oi.product_name', 'oi.order_reference', 'oi.quantity as item_qty')
            ->distinct()
            ->get();

        // Get recipe quantities for this ingredient
        $recipes = DB::table('product_recipes')
            ->where('ingredient_id', $ingredientId)
            ->pluck('quantity', 'product_id')
            ->toArray();

        // Aggregate by product (deduplicate by order_reference + product_id)
        $productMap = [];
        $seen = [];
        foreach ($transactions as $tx) {
            $dedupKey = $tx->order_reference . '-' . $tx->product_id;
            if (isset($seen[$dedupKey])) {
                continue;
            }
            $seen[$dedupKey] = true;

            $recipeQty = $recipes[$tx->product_id] ?? null;
            if ($recipeQty === null) {
                continue;
            }

            $key = $tx->product_id;
            if (!isset($productMap[$key])) {
                $productMap[$key] = [
                    'product_name' => $tx->product_name,
                    'times_sold' => 0,
                    'recipe_qty' => round((float) $recipeQty, 3),
                ];
            }
            $productMap[$key]['times_sold'] += $tx->item_qty;
        }

        // Get ingredient info
        $ingredient = DB::table('ingredients')->where('id', $ingredientId)->first();
        $unit = $ingredient->unit ?? 'unidad';
        $costPerUnit = (float) ($ingredient->cost_per_unit ?? 0);

        // Build response
        $products = array_values(array_map(function ($p) use ($unit, $costPerUnit) {
            $totalConsumed = $p['times_sold'] * $p['recipe_qty'];
            return [
                'product_name' => $p['product_name'],
                'times_sold' => $p['times_sold'],
                'recipe_qty' => $p['recipe_qty'],
                'total_consumed' => round($totalConsumed, 3),
                'unit' => $unit,
                'cost' => round($totalConsumed * $costPerUnit),
            ];
        }, $productMap));

        usort($products, fn($a, $b) => $b['cost'] <=> $a['cost']);

        return ['products' => $products];
    }

    /**
     * Monthly aggregates for the last N months (includes nómina from payroll).
     *
     * @return array<int, array{month: string, label: string, total_sales: float, total_cost: float, total_delivery: float, total_nomina: float, resultado: float}>
     */
    public function getMonthlyAggregates(int $months = 6): array
    {
        $meses = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];

        // Use Chile timezone for correct month boundaries (UTC-3/UTC-4)
        $rows = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->where('created_at', '>=', now()->subMonths($months)->startOfMonth())
            ->groupByRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-03:00'), '%Y-%m')")
            ->selectRaw("
                DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-03:00'), '%Y-%m') as month,
                COALESCE(SUM(installment_amount) - SUM(COALESCE(delivery_fee, 0)), 0) as total_sales,
                COALESCE(SUM(COALESCE(delivery_fee, 0)), 0) as total_delivery
            ")
            ->orderBy('month')
            ->get();

        return $rows->map(function ($row) use ($meses) {
            $monthNum = (int) substr($row->month, 5, 2);

            // Cost from inventory_transactions (same source as CMV breakdown)
            $cmvData = $this->getCmvBreakdown('month', $row->month);
            $cost = $cmvData['total_cmv'];

            // Nómina from pagos_nomina (column: mes, type: date)
            $nomina = (float) DB::table('pagos_nomina')
                ->whereRaw("DATE_FORMAT(mes, '%Y-%m') = ?", [$row->month])
                ->sum('monto');

            // If no payroll data, try compras with tipo_compra = 'nomina'
            if ($nomina === 0.0) {
                $nomina = (float) DB::table('compras')
                    ->where('tipo_compra', 'nomina')
                    ->whereRaw("DATE_FORMAT(fecha_compra, '%Y-%m') = ?", [$row->month])
                    ->sum('monto_total');
            }

            // If still no data, use NominaService as fallback for any month
            $isProjected = false;
            if ($nomina === 0.0) {
                try {
                    $nominaService = app(\App\Services\Payroll\NominaService::class);
                    $raw = $nominaService->getResumen($row->month);
                    $nomina = collect($raw['ruta11']['personal'] ?? [])
                        ->filter(fn ($e) => ! str_contains($e['personal']->rol ?? '', 'dueño'))
                        ->sum(fn ($e) => $e['liquidacion']['total']);
                } catch (\Throwable $e) {
                    // Fallback to projection if NominaService fails
                }

                // If still 0, project from avg of last 3 months
                if ($nomina === 0.0) {
                    $avgNomina = (float) DB::table('pagos_nomina')
                        ->where('mes', '<', $row->month . '-01')
                        ->orderByDesc('mes')
                        ->limit(3)
                        ->avg('monto');
                    if ($avgNomina > 0) {
                        $nomina = round($avgNomina);
                        $isProjected = true;
                    }
                }
            }

            $sales = (float) $row->total_sales;
            $delivery = (float) $row->total_delivery;

            // OPEX: gas, limpieza, mermas (same sources as DashboardController)
            $gas = (float) DB::table('compras')
                ->where('tipo_compra', 'gas')
                ->whereRaw("DATE_FORMAT(fecha_compra, '%Y-%m') = ?", [$row->month])
                ->sum('monto_total');

            $limpieza = (float) DB::table('compras_detalle as cd')
                ->join('compras as c', 'cd.compra_id', '=', 'c.id')
                ->join('ingredients as i', 'cd.ingrediente_id', '=', 'i.id')
                ->where('i.category', 'Limpieza')
                ->whereRaw("DATE_FORMAT(c.fecha_compra, '%Y-%m') = ?", [$row->month])
                ->sum('cd.subtotal');

            $mermas = (float) DB::table('mermas')
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$row->month])
                ->sum('cost');

            $totalOpex = $nomina + $gas + $limpieza + $mermas;
            $resultado = $sales - $cost - $totalOpex;

            return [
                'month'          => $row->month,
                'label'          => $meses[$monthNum - 1] ?? $row->month,
                'total_sales'    => $sales,
                'total_cost'     => $cost,
                'total_delivery' => $delivery,
                'total_nomina'   => $nomina,
                'total_gas'      => $gas,
                'total_limpieza' => $limpieza,
                'total_mermas'   => $mermas,
                'nomina_projected' => $isProjected,
                'resultado'      => $resultado,
            ];
        })->toArray();
    }

    /**
     * Detect order source from order_number prefix.
     */
    private function detectSource(string $orderNumber): string
    {
        if (str_starts_with($orderNumber, 'CAJA-')) {
            return 'caja';
        }
        if (str_starts_with($orderNumber, 'PYA-')) {
            return 'pedidosya';
        }

        return 'app';
    }
}
