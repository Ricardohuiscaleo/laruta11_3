<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalisisController extends Controller
{
    public function resumen(Request $request): JsonResponse
    {
        $monthParam = $request->query('month');
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $baseDate = \Carbon\Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth();
        } else {
            $baseDate = now();
        }
        $mes = $baseDate->format('Y-m');
        $mesInicio = $baseDate->copy()->startOfMonth();
        $mesFin = $baseDate->copy()->endOfMonth();

        // Monthly sales & costs
        $ventas = $this->ventasDelMes($mesInicio, $mesFin);
        $compras = (float) DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$mesInicio, $mesFin])
            ->sum('monto_total');

        // Hour distribution
        $horas = $this->horasConVentas($mesInicio, $mesFin);

        // Top/bottom products
        $topProductos = $this->topProductos($mesInicio, $mesFin, 10);
        $bottomProductos = $this->bottomProductos($mesInicio, $mesFin, 10);

        // Delivery impact
        $deliveryImpact = $this->deliveryImpact($mesInicio, $mesFin);

        // Day of week distribution
        $diaSemana = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$mesInicio, $mesFin])
            ->selectRaw("DATE_FORMAT(created_at, '%W') as dia")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->groupByRaw("DAYOFWEEK(created_at), DATE_FORMAT(created_at, '%W')")
            ->orderByRaw('DAYOFWEEK(created_at)')
            ->get()
            ->map(fn($r) => [
                'dia' => $r->dia,
                'ordenes' => (int) $r->ordenes,
                'ingresos' => (float) $r->ingresos,
            ]);

        // Monthly history (last 12 months)
        $historial = $this->historialMensual();

        // Active vs inactive products
        $productosState = DB::table('products')
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as activos")
            ->selectRaw("SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactivos")
            ->first();

        return response()->json(['success' => true, 'data' => [
            'mes' => $mes,
            'ventas_totales' => $ventas['total'],
            'total_ordenes' => $ventas['ordenes'],
            'ticket_promedio' => $ventas['ordenes'] > 0 ? round($ventas['total'] / $ventas['ordenes']) : 0,
            'compras_mes' => $compras,
            'resultado_estimado' => round($ventas['total'] - $compras),
            'horas' => $horas,
            'top_productos' => $topProductos,
            'bottom_productos' => $bottomProductos,
            'delivery_impact' => $deliveryImpact,
            'dia_semana' => $diaSemana,
            'historial_mensual' => $historial,
            'productos' => [
                'total' => (int) $productosState->total,
                'activos' => (int) $productosState->activos,
                'inactivos' => (int) $productosState->inactivos,
            ],
        ]]);
    }

    private function ventasDelMes($inicio, $fin): array
    {
        $row = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as total')
            ->selectRaw('COUNT(*) as ordenes')
            ->first();
        return [
            'total' => (float) ($row->total ?? 0),
            'ordenes' => (int) ($row->ordenes ?? 0),
        ];
    }

    private function horasConVentas($inicio, $fin): array
    {
        $rows = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-04:00'), '%H') as hora")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->groupByRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-04:00'), '%H')")
            ->orderBy('hora')
            ->get();

        $horas = [];
        foreach ($rows as $r) {
            $horas[] = [
                'hora' => str_pad($r->hora, 2, '0', STR_PAD_LEFT),
                'ordenes' => (int) $r->ordenes,
                'ingresos' => (float) $r->ingresos,
            ];
        }
        return $horas;
    }

    private function topProductos($inicio, $fin, int $limit): array
    {
        return DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as cantidad, COUNT(DISTINCT oi.order_reference) as pedidos, SUM(oi.subtotal) as ingresos')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('cantidad')
            ->limit($limit)
            ->get()
            ->map(fn($r) => [
                'id' => (int) $r->product_id,
                'nombre' => $r->product_name,
                'cantidad' => (int) $r->cantidad,
                'pedidos' => (int) $r->pedidos,
                'ingresos' => (float) $r->ingresos,
            ])
            ->toArray();
    }

    private function bottomProductos($inicio, $fin, int $limit): array
    {
        $productosVendidos = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as cantidad')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->pluck('cantidad', 'product_id');

        $vendidos = collect($productosVendidos);

        $menosVendidos = DB::table('products')
            ->where('is_active', 1)
            ->whereNotIn('id', $vendidos->keys())
            ->limit($limit)
            ->get(['id', 'name', 'price'])
            ->map(fn($r) => [
                'id' => $r->id,
                'nombre' => $r->name,
                'cantidad' => 0,
                'precio' => (float) $r->price,
            ])
            ->toArray();

        if (count($menosVendidos) < $limit) {
            $pocosVendidos = DB::table('products')
                ->where('is_active', 1)
                ->whereIn('id', $vendidos->keys())
                ->whereNotIn('id', collect($menosVendidos)->pluck('id'))
                ->get(['id', 'name'])
                ->map(fn($r) => [
                    'id' => $r->id,
                    'nombre' => $r->name,
                    'cantidad' => (int) ($vendidos[$r->id] ?? 0),
                    'precio' => 0,
                ])
                ->sortBy('cantidad')
                ->values()
                ->take($limit)
                ->toArray();

            $menosVendidos = $menosVendidos + $pocosVendidos;
        }

        return array_values($menosVendidos);
    }

    private function deliveryImpact($inicio, $fin): array
    {
        $rows = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw("COALESCE(NULLIF(delivery_type, ''), 'pickup') as tipo")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->selectRaw('COALESCE(SUM(delivery_fee), 0) as delivery_fee')
            ->groupBy('tipo')
            ->get();

        $total = (float) $rows->sum('ingresos');
        $totalOrdenes = (int) $rows->sum('ordenes');

        $tipos = $rows->map(fn($r) => [
            'tipo' => $r->tipo,
            'ordenes' => (int) $r->ordenes,
            'pct_ordenes' => $totalOrdenes > 0 ? round((int) $r->ordenes / $totalOrdenes * 100, 1) : 0,
            'ingresos' => (float) $r->ingresos,
            'ingresos_sin_envio' => round((float) $r->ingresos - (float) ($r->delivery_fee ?? 0)),
            'fee_envio' => (float) ($r->delivery_fee ?? 0),
        ]);

        return ['tipos' => $tipos, 'total_delivery_fees' => $tipos->sum('fee_envio')];
    }

    private function historialMensual(): array
    {
        $rows = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->selectRaw('COALESCE(SUM(delivery_fee), 0) as delivery_fee')
            ->groupBy('mes')
            ->orderBy('mes')
            ->get();

        $meses = [];
        foreach ($rows as $r) {
            $meses[] = [
                'mes' => $r->mes,
                'ordenes' => (int) $r->ordenes,
                'ingresos' => (float) $r->ingresos,
                'fee_envio' => (float) $r->delivery_fee,
            ];
        }

        // Fill missing months with zeros
        $result = [];
        for ($i = 11; $i >= 0; $i--) {
            $mes = now()->subMonths($i)->format('Y-m');
            $found = collect($meses)->firstWhere('mes', $mes);
            $result[] = $found ?: ['mes' => $mes, 'ordenes' => 0, 'ingresos' => 0, 'fee_envio' => 0];
        }

        return $result;
    }

    public function mensual(Request $request): JsonResponse
    {
        $monthParam = $request->query('month');
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $baseDate = \Carbon\Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth();
        } else {
            $baseDate = now();
        }
        $mesInicio = $baseDate->copy()->startOfMonth();
        $mesFin = $baseDate->copy()->endOfMonth();

        $ventas = $this->ventasDelMes($mesInicio, $mesFin);

        // Products this month
        $productos = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$mesInicio, $mesFin])
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as cantidad, SUM(oi.subtotal) as ingresos, COALESCE(SUM(oi.item_cost * oi.quantity), 0) as costo')
            ->groupBy('oi.product_id', 'oi.product_name')
            ->orderByDesc('cantidad')
            ->get()
            ->map(fn($r) => [
                'id' => (int) $r->product_id,
                'nombre' => $r->product_name,
                'cantidad' => (int) $r->cantidad,
                'ingresos' => (float) $r->ingresos,
                'costo' => (float) $r->costo,
                'margen' => (float) $r->ingresos > 0 ? round(((float) $r->ingresos - (float) $r->costo) / (float) $r->ingresos * 100, 1) : 0,
            ]);

        $compras = DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$mesInicio, $mesFin])
            ->selectRaw('tipo_compra, COUNT(*) as cantidad, SUM(monto_total) as total')
            ->groupBy('tipo_compra')
            ->get()
            ->map(fn($r) => [
                'tipo' => $r->tipo_compra,
                'cantidad' => (int) $r->cantidad,
                'total' => (float) $r->total,
            ]);

        $horas = $this->horasConVentas($mesInicio, $mesFin);

        return response()->json(['success' => true, 'data' => [
            'mes' => $baseDate->format('Y-m'),
            'ventas' => $ventas,
            'productos' => $productos,
            'compras' => $compras,
            'horas' => $horas,
        ]]);
    }
}
