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
