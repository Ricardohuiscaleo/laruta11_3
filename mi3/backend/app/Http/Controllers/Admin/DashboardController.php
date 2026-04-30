<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payroll\NominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NominaService $nominaService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        // Support navigating to previous months: ?month=2026-03
        $monthParam = $request->query('month');
        if ($monthParam && preg_match('/^\d{4}-\d{2}$/', $monthParam)) {
            $baseDate = \Carbon\Carbon::createFromFormat('Y-m-d', $monthParam . '-01')->startOfMonth();
        } else {
            $baseDate = now();
        }

        $mes = $baseDate->format('Y-m');
        $mesInicio = $baseDate->copy()->startOfMonth();
        $mesFin = $baseDate->copy()->endOfMonth();

        $data = [
            'ventas_mes' => 0,
            'compras_mes' => 0,
            'nomina_mes' => 0,
            'resultado_bruto' => 0,
            'pnl' => [
                'ingresos' => [
                    'ventas_netas' => 0,
                    'total_ordenes' => 0,
                    'ticket_promedio' => 0,
                ],
                'costo_ventas' => [
                    'costo_ingredientes' => 0,
                    'costo_ingredientes_pct' => 0,
                    'margen_bruto' => 0,
                    'margen_bruto_pct' => 0,
                ],
                'gastos_operacion' => [
                    'nomina_ruta11' => 0,
                    'nomina_ruta11_pct' => 0,
                    'gas' => 0,
                    'gas_pct' => 0,
                    'limpieza' => 0,
                    'limpieza_pct' => 0,
                    'mermas' => 0,
                    'mermas_pct' => 0,
                    'otros_gastos' => 0,
                    'otros_gastos_pct' => 0,
                    'total_opex' => 0,
                    'total_opex_pct' => 0,
                ],
                'flujo_caja' => [
                    'compras_mes' => 0,
                ],
                'resultado' => [
                    'resultado_neto' => 0,
                    'resultado_neto_pct' => 0,
                ],
                'meta' => [
                    'meta_mensual' => 0,
                    'porcentaje_meta' => 0,
                    'ventas_proyectadas' => 0,
                    'meta_equilibrio' => 0,
                ],
            ],
        ];

        $isCurrentMonth = $baseDate->format('Y-m') === now()->format('Y-m');

        // Fetch ventas — current month from caja3, historical from VentasService
        if ($isCurrentMonth) {
            try {
                $res = Http::timeout(8)->get('https://caja.laruta11.cl/api/get_dashboard_cards.php');
                if ($res->successful()) {
                    $cards = $res->json();
                    if ($cards['success'] ?? false) {
                        $d = $cards['data'];
                        $ventasReal = (float) ($d['ventas']['real'] ?? 0);
                        $totalCompras = (float) ($d['compras']['total_mes'] ?? 0);
                        $totalOrdenes = (int) ($d['ventas']['total_ordenes'] ?? 0);
                        $ticketPromedio = (float) ($d['ventas']['ticket_promedio'] ?? 0);
                        $metaMensual = (float) ($d['ventas']['meta_mensual'] ?? 0);
                        $pctMeta = (float) ($d['ventas']['porcentaje_meta'] ?? 0);
                        $ventasProyectadas = (float) ($d['ventas']['proyectado'] ?? 0);

                        $data['ventas_mes'] = $ventasReal;
                        $data['compras_mes'] = $totalCompras;

                        $data['pnl']['ingresos']['ventas_netas'] = $ventasReal;
                        $data['pnl']['ingresos']['total_ordenes'] = $totalOrdenes;
                        $data['pnl']['ingresos']['ticket_promedio'] = $ticketPromedio;

                        $data['pnl']['flujo_caja']['compras_mes'] = $totalCompras;

                    $data['pnl']['meta']['meta_mensual'] = $metaMensual;
                    $data['pnl']['meta']['porcentaje_meta'] = $pctMeta;
                    $data['pnl']['meta']['ventas_proyectadas'] = $ventasProyectadas;
                }
            }
        } catch (\Exception $e) {
            // Silently fail — dashboard still shows with zeros
        }
        } else {
            // Historical month — query tuu_orders directly
            $histStart = $mesInicio->copy()->setTimezone('America/Santiago')->startOfDay()->utc();
            $histEnd = $mesFin->copy()->setTimezone('America/Santiago')->endOfDay()->utc();

            $histRow = \Illuminate\Support\Facades\DB::table('tuu_orders')
                ->where('payment_status', 'paid')
                ->where('order_number', 'NOT LIKE', 'RL6-%')
                ->whereBetween('created_at', [$histStart, $histEnd])
                ->selectRaw('COALESCE(SUM(installment_amount) - SUM(COALESCE(delivery_fee, 0)), 0) as net, COUNT(*) as cnt')
                ->first();

            $histCost = (float) \Illuminate\Support\Facades\DB::table('tuu_order_items as oi')
                ->join('tuu_orders as o', 'oi.order_reference', '=', 'o.order_number')
                ->where('o.payment_status', 'paid')
                ->where('o.order_number', 'NOT LIKE', 'RL6-%')
                ->whereBetween('o.created_at', [$histStart, $histEnd])
                ->selectRaw('COALESCE(SUM(oi.item_cost * oi.quantity), 0) as c')
                ->value('c');

            $ventasReal = (float) ($histRow->net ?? 0);
            $totalOrdenes = (int) ($histRow->cnt ?? 0);
            $data['ventas_mes'] = $ventasReal;
            $data['pnl']['ingresos']['ventas_netas'] = $ventasReal;
            $data['pnl']['ingresos']['total_ordenes'] = $totalOrdenes;
            $data['pnl']['ingresos']['ticket_promedio'] = $totalOrdenes > 0 ? round($ventasReal / $totalOrdenes) : 0;

            // CMV for historical months: use VentasService (same source as breakdown)
            try {
                $ventasService = app(\App\Services\Ventas\VentasService::class);
                $cmvData = $ventasService->getCmvBreakdown('month', $mes);
                $data['pnl']['costo_ventas']['costo_ingredientes'] = $cmvData['total_cmv'];
            } catch (\Exception $e) {
                $data['pnl']['costo_ventas']['costo_ingredientes'] = $histCost;
            }
        }

        // CMV: from VentasService (inventory_transactions — single source of truth)
        if ($isCurrentMonth) {
            try {
                $ventasService = app(\App\Services\Ventas\VentasService::class);
                $cmvData = $ventasService->getCmvBreakdown('month');
                $data['pnl']['costo_ventas']['costo_ingredientes'] = $cmvData['total_cmv'];
            } catch (\Exception $e) {
                // Silently fail
            }
        } // end isCurrentMonth CMV block

        // Nómina: pagos_nomina for historical, NominaService for current month
        try {
            $totalNomina = 0;

            // First try pagos_nomina (actual payments — source of truth for historical)
            $totalNomina = (float) \Illuminate\Support\Facades\DB::table('pagos_nomina')
                ->whereRaw("DATE_FORMAT(mes, '%Y-%m') = ?", [$mes])
                ->sum('monto');

            // Fallback: compras with tipo_compra = 'nomina'
            if ($totalNomina === 0.0) {
                $totalNomina = (float) \Illuminate\Support\Facades\DB::table('compras')
                    ->where('tipo_compra', 'nomina')
                    ->whereRaw("DATE_FORMAT(fecha_compra, '%Y-%m') = ?", [$mes])
                    ->sum('monto_total');
            }

            // Current month: use NominaService (calculated from shifts/contracts)
            if ($totalNomina === 0.0 && $isCurrentMonth) {
                $raw = $this->nominaService->getResumen($mes);
                $totalNomina = collect($raw['ruta11']['personal'] ?? [])
                    ->filter(fn ($e) => ! str_contains($e['personal']->rol ?? '', 'dueño'))
                    ->sum(fn ($e) => $e['liquidacion']['total']);
            }

            $data['nomina_mes'] = $totalNomina;
            $data['pnl']['gastos_operacion']['nomina_ruta11'] = $totalNomina;
        } catch (\Exception $e) {
            // Silently fail
        }

        // OPEX lines: gas/limpieza from compras (compra=gasto), mermas, otros from consumption
        try {
            // Gas: compras del mes con detalle
            $gasItems = \Illuminate\Support\Facades\DB::table('compras')
                ->where('tipo_compra', 'gas')
                ->whereRaw("DATE_FORMAT(fecha_compra, '%Y-%m') = ?", [$mes])
                ->select('id', 'proveedor', 'monto_total', 'fecha_compra')
                ->orderBy('fecha_compra')
                ->get()
                ->map(fn ($r) => ['proveedor' => $r->proveedor ?? 'Gas', 'monto' => (float) $r->monto_total, 'fecha' => $r->fecha_compra])
                ->toArray();
            $gasCompras = array_sum(array_column($gasItems, 'monto'));
            $data['pnl']['gastos_operacion']['gas'] = $gasCompras;
            $data['pnl']['gastos_operacion']['gas_items'] = $gasItems;

            // Limpieza: compras de ingredientes con categoría "Limpieza"
            $limpiezaItems = \Illuminate\Support\Facades\DB::table('compras_detalle as cd')
                ->join('compras as c', 'cd.compra_id', '=', 'c.id')
                ->join('ingredients as i', 'cd.ingrediente_id', '=', 'i.id')
                ->where('i.category', 'Limpieza')
                ->whereRaw("DATE_FORMAT(c.fecha_compra, '%Y-%m') = ?", [$mes])
                ->select('cd.nombre_item', 'cd.cantidad', 'cd.unidad', 'cd.subtotal', 'c.fecha_compra')
                ->orderBy('cd.subtotal', 'desc')
                ->get()
                ->map(fn ($r) => ['proveedor' => $r->nombre_item, 'monto' => (float) $r->subtotal, 'fecha' => $r->fecha_compra, 'cantidad' => (float) $r->cantidad, 'unidad' => $r->unidad])
                ->toArray();
            $limpiezaCompras = array_sum(array_column($limpiezaItems, 'monto'));
            $data['pnl']['gastos_operacion']['limpieza'] = $limpiezaCompras;
            $data['pnl']['gastos_operacion']['limpieza_items'] = $limpiezaItems;

            // Otros gastos: consumos reales registrados (no retroactivos)
            $consumos = \Illuminate\Support\Facades\DB::table('inventory_transactions as it')
                ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
                ->where('it.transaction_type', 'consumption')
                ->whereBetween('it.created_at', [$mesInicio, $mesFin])
                ->whereNotIn('i.category', ['Gas', 'Limpieza'])
                ->select(\Illuminate\Support\Facades\DB::raw('SUM(ABS(it.quantity) * i.cost_per_unit) as total_cost'))
                ->value('total_cost');
            $data['pnl']['gastos_operacion']['otros_gastos'] = (float) ($consumos ?? 0);

            // Mermas del mes con detalle
            $mermaItems = \Illuminate\Support\Facades\DB::table('mermas')
                ->whereBetween('created_at', [$mesInicio, $mesFin])
                ->select('id', 'item_name', 'quantity', 'unit', 'cost', 'reason', 'created_at')
                ->orderByDesc('cost')
                ->get()
                ->map(fn ($r) => ['name' => $r->item_name, 'quantity' => (float) $r->quantity, 'unit' => $r->unit, 'cost' => (float) $r->cost, 'reason' => $r->reason, 'fecha' => $r->created_at])
                ->toArray();
            $mermas = array_sum(array_column($mermaItems, 'cost'));
            $data['pnl']['gastos_operacion']['mermas'] = $mermas;
            $data['pnl']['gastos_operacion']['mermas_items'] = $mermaItems;

            // Payment breakdown for the selected month (for Ventas Netas chevron)
            $paymentBreakdown = \Illuminate\Support\Facades\DB::table('tuu_orders')
                ->where('payment_status', 'paid')
                ->where('order_number', 'NOT LIKE', 'RL6-%')
                ->whereBetween('created_at', [$mesInicio->copy()->setTimezone('America/Santiago')->startOfDay()->utc(), $mesFin->copy()->setTimezone('America/Santiago')->endOfDay()->utc()])
                ->groupBy('payment_method')
                ->selectRaw('payment_method as method, COUNT(*) as order_count, COALESCE(SUM(installment_amount) - SUM(COALESCE(delivery_fee, 0)), 0) as total_sales')
                ->get()
                ->map(fn ($r) => ['method' => $r->method ?? 'otros', 'order_count' => (int) $r->order_count, 'total_sales' => (float) $r->total_sales])
                ->toArray();
            $data['pnl']['ingresos']['payment_breakdown'] = $paymentBreakdown;
        } catch (\Exception $e) {
            // Silently fail
        }

        // Calculate derived values
        $ventas = $data['pnl']['ingresos']['ventas_netas'];
        $cogs = $data['pnl']['costo_ventas']['costo_ingredientes'];
        $nomina = $data['pnl']['gastos_operacion']['nomina_ruta11'];
        $gas = $data['pnl']['gastos_operacion']['gas'];
        $limpieza = $data['pnl']['gastos_operacion']['limpieza'];
        $mermasVal = $data['pnl']['gastos_operacion']['mermas'];
        $otrosGastos = $data['pnl']['gastos_operacion']['otros_gastos'];

        $margenBruto = $ventas - $cogs;
        $data['pnl']['costo_ventas']['margen_bruto'] = $margenBruto;
        $margenBrutoPct = $ventas > 0 ? round(($margenBruto / $ventas) * 100, 1) : 0;
        $data['pnl']['costo_ventas']['margen_bruto_pct'] = $margenBrutoPct;
        $data['pnl']['costo_ventas']['costo_ingredientes_pct'] = $ventas > 0
            ? round(($cogs / $ventas) * 100, 1) : 0;

        $totalOpex = $nomina + $gas + $limpieza + $mermasVal + $otrosGastos;
        $data['pnl']['gastos_operacion']['total_opex'] = $totalOpex;

        // Percentages for each OPEX line
        if ($ventas > 0) {
            $data['pnl']['gastos_operacion']['nomina_ruta11_pct'] = round(($nomina / $ventas) * 100, 1);
            $data['pnl']['gastos_operacion']['gas_pct'] = round(($gas / $ventas) * 100, 1);
            $data['pnl']['gastos_operacion']['limpieza_pct'] = round(($limpieza / $ventas) * 100, 1);
            $data['pnl']['gastos_operacion']['mermas_pct'] = round(($mermasVal / $ventas) * 100, 1);
            $data['pnl']['gastos_operacion']['otros_gastos_pct'] = round(($otrosGastos / $ventas) * 100, 1);
            $data['pnl']['gastos_operacion']['total_opex_pct'] = round(($totalOpex / $ventas) * 100, 1);
        }

        $resultadoNeto = $margenBruto - $totalOpex;
        $data['pnl']['resultado']['resultado_neto'] = $resultadoNeto;
        $data['pnl']['resultado']['resultado_neto_pct'] = $ventas > 0
            ? round(($resultadoNeto / $ventas) * 100, 1) : 0;

        $data['resultado_bruto'] = $resultadoNeto;

        // Meta equilibrio = total_opex / (margen_bruto_pct / 100)
        $data['pnl']['meta']['meta_equilibrio'] = $margenBrutoPct > 0
            ? round($totalOpex / ($margenBrutoPct / 100)) : 0;

        return response()->json(['success' => true, 'data' => $data, 'month' => $mes]);
    }
}
