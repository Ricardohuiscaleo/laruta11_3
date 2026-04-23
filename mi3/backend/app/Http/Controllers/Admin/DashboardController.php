<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Payroll\NominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    public function __construct(
        private readonly NominaService $nominaService,
    ) {}

    public function index(): JsonResponse
    {
        $mes = now()->format('Y-m');
        $mesInicio = now()->startOfMonth();
        $mesFin = now()->endOfMonth();

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

        // Fetch ventas/meta/proyección from caja3 (keep HTTP call for smart projection)
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

        // CMV: direct DB query instead of HTTP call to get_sales_analytics.php
        try {
            $costoIngredientes = (float) \Illuminate\Support\Facades\DB::table('tuu_order_items as oi')
                ->join('tuu_orders as o', 'oi.order_id', '=', 'o.id')
                ->where('o.payment_status', 'paid')
                ->whereBetween('o.created_at', [$mesInicio, $mesFin])
                ->selectRaw('SUM(oi.item_cost * oi.quantity) as total')
                ->value('total') ?? 0;

            $data['pnl']['costo_ventas']['costo_ingredientes'] = $costoIngredientes;
        } catch (\Exception $e) {
            // Silently fail
        }

        // Nómina: solo centro ruta11, excluyendo dueño
        try {
            $raw = $this->nominaService->getResumen($mes);

            $totalNomina = collect($raw['ruta11']['personal'] ?? [])
                ->filter(fn ($e) => ! str_contains($e['personal']->rol ?? '', 'dueño'))
                ->sum(fn ($e) => $e['liquidacion']['sueldo_base']);

            $data['nomina_mes'] = $totalNomina;
            $data['pnl']['gastos_operacion']['nomina_ruta11'] = $totalNomina;
        } catch (\Exception $e) {
            // Silently fail
        }

        // OPEX lines: gas, limpieza, mermas, otros_gastos from DB
        try {
            // Consumos por categoría del mes
            $consumos = \Illuminate\Support\Facades\DB::table('inventory_transactions as it')
                ->join('ingredients as i', 'it.ingredient_id', '=', 'i.id')
                ->where('it.transaction_type', 'consumption')
                ->whereBetween('it.created_at', [$mesInicio, $mesFin])
                ->select('i.category', \Illuminate\Support\Facades\DB::raw('SUM(ABS(it.quantity) * i.cost_per_unit) as total_cost'))
                ->groupBy('i.category')
                ->get();

            foreach ($consumos as $row) {
                $cost = (float) $row->total_cost;
                match ($row->category) {
                    'Gas' => $data['pnl']['gastos_operacion']['gas'] = $cost,
                    'Limpieza' => $data['pnl']['gastos_operacion']['limpieza'] = $cost,
                    'Servicios' => $data['pnl']['gastos_operacion']['otros_gastos'] += $cost,
                    default => $data['pnl']['gastos_operacion']['otros_gastos'] += $cost,
                };
            }

            // Mermas del mes
            $mermas = (float) \Illuminate\Support\Facades\DB::table('mermas')
                ->whereBetween('created_at', [$mesInicio, $mesFin])
                ->sum('cost');
            $data['pnl']['gastos_operacion']['mermas'] = $mermas;
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

        return response()->json(['success' => true, 'data' => $data]);
    }
}
