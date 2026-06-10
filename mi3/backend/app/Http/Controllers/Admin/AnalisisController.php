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

    private function horasConCMV($inicio, $fin): array
    {
        $ventas = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-04:00'), '%H') as hora")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->groupByRaw("DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', '-04:00'), '%H')")
            ->orderBy('hora')
            ->get()
            ->keyBy('hora');

        $cmv = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(CONVERT_TZ(o.created_at, '+00:00', '-04:00'), '%H') as hora")
            ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as cmv')
            ->groupByRaw("DATE_FORMAT(CONVERT_TZ(o.created_at, '+00:00', '-04:00'), '%H')")
            ->get()
            ->keyBy('hora');

        $dias = (int) max($inicio->diffInDays($fin), 1);

        // Staff diario: $1,500,000/mes / 30 = $50,000/día. Arriendo: $500,000/mes / 30 = $16,667/día.
        // 3 personas en peak (18-22h), 1 persona en valle (15-17, 23-00), 0 en madrugada.
        // Persona-horas por día: 3×4h + 1×5h = 12 + 5 = 17 persona-horas
        // Costo por persona-hora: $50,000 / 17 = $2,941
        $staffPorHora = fn(string $h): int => match(true) {
            $h >= '18' && $h <= '22' => (int) round(3 * 2941),
            ($h >= '15' && $h <= '17') || $h === '23' || $h === '00' => (int) round(1 * 2941),
            default => 0,
        };
        $fijoPorHora = fn(string $h): int => match(true) {
            $h >= '15' || $h <= '02' => (int) round(16667 / 11),
            default => 0,
        };

        $horas = [];
        for ($h = 0; $h < 24; $h++) {
            $hPad = str_pad($h, 2, '0', STR_PAD_LEFT);
            $v = $ventas->get($hPad);
            $c = $cmv->get($hPad);
            $ingresos = (float) ($v->ingresos ?? 0);
            $cmvVal = (float) ($c->cmv ?? 0);
            $staffDia = $staffPorHora($hPad);
            $fijoDia = $fijoPorHora($hPad);

            $horas[] = [
                'hora' => $hPad,
                'ordenes' => (int) ($v->ordenes ?? 0),
                'ingresos' => $ingresos,
                'cmv' => (float) round($cmvVal),
                'costo_staff' => (int) round($staffDia * $dias),
                'costo_fijo' => (int) round($fijoDia * $dias),
                'costo_total' => (int) round(($staffDia + $fijoDia) * $dias),
                'resultado' => (int) round($ingresos - $cmvVal - ($staffDia + $fijoDia) * $dias),
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

    public function anual(): JsonResponse
    {
        $inicio = now()->subMonths(12)->startOfMonth();
        $fin = now();

        $resumen = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ventas')
            ->selectRaw('COALESCE(SUM(delivery_fee), 0) as delivery_fees')
            ->first();

        $comprasTotales = (float) DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$inicio, $fin])
            ->sum('monto_total');

        $cmv = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as costo')
            ->selectRaw('COALESCE(SUM(oi.subtotal), 0) as ventas')
            ->first();

        $costoCmv = (float) ($cmv->costo ?? 0);
        $ventasCmv = (float) ($cmv->ventas ?? 0);
        $pctMargen = $ventasCmv > 0 ? round(($ventasCmv - $costoCmv) / $ventasCmv * 100, 1) : 0;

        // fuga_compras solo en meses con compras registradas (excluye meses sin data de compras)
        $mesesConCompras = DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(fecha_compra, '%Y-%m') as mes")
            ->distinct()->pluck('mes');
        $cmvConCompras = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(o.created_at, '%Y-%m') as mes")
            ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as costo')
            ->groupBy('mes')
            ->whereIn(DB::raw("DATE_FORMAT(o.created_at, '%Y-%m')"), $mesesConCompras)
            ->get()
            ->sum('costo');
        $comprasConVentas = DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$inicio, $fin])
            ->whereIn(DB::raw("DATE_FORMAT(fecha_compra, '%Y-%m')"), $mesesConCompras)
            ->sum('monto_total');
        $fugaCompras = round((float)$comprasConVentas - (float)$cmvConCompras);

        $historial = $this->historialConCompras($inicio, $fin);

        $topProductos = $this->topProductosAnual($inicio, $fin, 15);

        $horas = $this->horasConCMV($inicio, $fin);

        $diaSemana = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(created_at, '%W') as dia")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ingresos')
            ->selectRaw('ROUND(COALESCE(SUM(installment_amount), 0) / NULLIF(COUNT(*), 0)) as ticket')
            ->groupByRaw("DAYOFWEEK(created_at), DATE_FORMAT(created_at, '%W')")
            ->orderByRaw('DAYOFWEEK(created_at)')
            ->get()
            ->map(fn($r) => [
                'dia' => $r->dia,
                'ordenes' => (int) $r->ordenes,
                'ingresos' => (float) $r->ingresos,
                'ticket' => (int) $r->ticket,
            ]);

        $productosState = DB::table('products')
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN is_active=1 THEN 1 ELSE 0 END) as activos, SUM(CASE WHEN is_active=0 THEN 1 ELSE 0 END) as inactivos")
            ->first();

        $recomendaciones = [];
        $horasMuertas = $this->horasSinVentas($horas);
        if (count($horasMuertas) > 5) {
            $recomendaciones[] = ['tipo' => 'horario', 'texto' => count($horasMuertas) . ' horas sin ventas en 12 meses. Horario operativo debe ser 18-23h. Reducción inmediata.', 'severidad' => 'alta'];
        }
        if ($fugaCompras > 100000) {
            $recomendaciones[] = ['tipo' => 'mermas', 'texto' => 'Fuga de $' . number_format($fugaCompras, 0, ',', '.') . ' en compras vs costo de productos vendidos. Mermas, sobre-stock o packaging sin asignar.', 'severidad' => 'alta'];
        }
        if ($historial->count() > 2) {
            $ultimos = $historial->slice(-3)->values();
            if ($ultimos[0]['ventas'] < $ultimos[1]['ventas'] * 0.8 && $ultimos[1]['ventas'] < $ultimos[2]['ventas'] * 0.8) {
                $recomendaciones[] = ['tipo' => 'tendencia', 'texto' => '2 meses consecutivos de caída en ventas. Tendencia bajista. Revisar estrategia comercial.', 'severidad' => 'alta'];
            }
        }
        $recomendaciones[] = ['tipo' => 'delivery', 'texto' => 'Fees de delivery acumulados: $' . number_format((float) $resumen->delivery_fees, 0, ',', '.') . ' en 12 meses. Promover pickup con descuento directo.', 'severidad' => 'media'];
        $recomendaciones[] = ['tipo' => 'productos', 'texto' => (int) $productosState->inactivos . ' productos inactivos de ' . (int) $productosState->total . '. Depurar catálogo o reactivar con estrategia.', 'severidad' => 'media'];

        return response()->json(['success' => true, 'data' => [
            'periodo' => ['desde' => $inicio->format('Y-m'), 'hasta' => now()->format('Y-m')],
            'resumen' => [
                'ordenes' => (int) ($resumen->ordenes ?? 0),
                'ventas' => (float) ($resumen->ventas ?? 0),
                'ticket' => ($resumen->ordenes ?? 0) > 0 ? round($resumen->ventas / $resumen->ordenes) : 0,
                'compras' => $comprasTotales,
                'delivery_fees' => (float) ($resumen->delivery_fees ?? 0),
                'cmv' => $costoCmv,
                'margen_bruto' => round($ventasCmv - $costoCmv),
                'pct_margen' => $pctMargen,
                'fuga_compras' => $fugaCompras,
                'pct_fuga' => $comprasTotales > 0 ? round($fugaCompras / $comprasTotales * 100, 1) : 0,
            ],
            'historial' => $historial,
            'top_productos' => $topProductos,
            'horas' => $horas,
            'horas_muertas' => $horasMuertas,
            'horas_activas' => count(array_filter($horas, fn($h) => $h['ordenes'] > 0)),
            'dia_semana' => $diaSemana,
            'productos' => ['total' => (int) $productosState->total, 'activos' => (int) $productosState->activos, 'inactivos' => (int) $productosState->inactivos],
            'recomendaciones' => $recomendaciones,
        ]]);
    }

    private function historialConCompras($inicio, $fin): \Illuminate\Support\Collection
    {
        $ventas = DB::table('tuu_orders')
            ->where('payment_status', 'paid')
            ->where('order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as mes")
            ->selectRaw('COUNT(*) as ordenes')
            ->selectRaw('COALESCE(SUM(installment_amount), 0) as ventas')
            ->selectRaw('COALESCE(SUM(delivery_fee), 0) as delivery_fees')
            ->groupBy('mes')->orderBy('mes')->get()
            ->keyBy('mes');

        $compras = DB::table('compras')
            ->where('estado', 'pagado')
            ->whereBetween('fecha_compra', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(fecha_compra, '%Y-%m') as mes")
            ->selectRaw('COALESCE(SUM(monto_total), 0) as compras')
            ->groupBy('mes')->orderBy('mes')->get()
            ->keyBy('mes');

        $cmv = DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw("DATE_FORMAT(o.created_at, '%Y-%m') as mes")
            ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as cmv')
            ->groupBy('mes')->orderBy('mes')->get()
            ->keyBy('mes');

        $result = collect();
        for ($i = 11; $i >= 0; $i--) {
            $mes = now()->subMonths($i)->format('Y-m');
            $v = $ventas->get($mes);
            $c = $compras->get($mes);
            $m = $cmv->get($mes);
            $ventasMes = (float) ($v->ventas ?? 0);
            $cmvMes = (float) ($m->cmv ?? 0);
            $result->push([
                'mes' => $mes,
                'ordenes' => (int) ($v->ordenes ?? 0),
                'ventas' => $ventasMes,
                'compras' => (float) ($c->compras ?? 0),
                'delivery_fees' => (float) ($v->delivery_fees ?? 0),
                'cmv' => $cmvMes,
                'margen' => round($ventasMes - $cmvMes),
                'pct_margen' => $ventasMes > 0 ? round(($ventasMes - $cmvMes) / $ventasMes * 100, 1) : 0,
                'ticket' => ($v->ordenes ?? 0) > 0 ? round($ventasMes / $v->ordenes) : 0,
            ]);
        }

        return $result;
    }

    private function topProductosAnual($inicio, $fin, int $limit): array
    {
        return DB::table('tuu_order_items as oi')
            ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
            ->where('o.payment_status', 'paid')
            ->where('o.order_number', 'NOT LIKE', 'RL6-%')
            ->whereBetween('o.created_at', [$inicio, $fin])
            ->selectRaw('oi.product_id, oi.product_name, SUM(oi.quantity) as cantidad, COUNT(DISTINCT oi.order_reference) as pedidos, SUM(oi.subtotal) as ingresos, COALESCE(SUM(oi.item_cost * oi.quantity), 0) as costo')
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
                'costo' => (float) $r->costo,
                'margen' => (float) $r->ingresos - (float) $r->costo,
                'pct_margen' => (float) $r->ingresos > 0 ? round(((float) $r->ingresos - (float) $r->costo) / (float) $r->ingresos * 100, 1) : 0,
            ])
            ->toArray();
    }

    private function horasSinVentas($horas): array
    {
        $todas = array_fill_keys(array_map(fn($i) => str_pad($i, 2, '0', STR_PAD_LEFT), range(0, 23)), 0);
        foreach ($horas as $h) {
            $todas[$h['hora']] = $h['ordenes'];
        }
        $muertas = [];
        foreach ($todas as $h => $c) {
            if ($c === 0) $muertas[] = $h . ':00';
        }
        return $muertas;
    }
}
