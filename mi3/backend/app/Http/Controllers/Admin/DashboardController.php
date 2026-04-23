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
                    'margen_bruto' => 0,
                    'margen_bruto_pct' => 0,
                ],
                'gastos_operacion' => [
                    'nomina_ruta11' => 0,
                    'total_opex' => 0,
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
                ],
            ],
        ];

        // Fetch detailed data from caja3
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

        // Fetch COGS from sales analytics
        try {
            $res2 = Http::timeout(8)->get('https://caja.laruta11.cl/api/get_sales_analytics.php', [
                'period' => 'month',
            ]);
            if ($res2->successful()) {
                $analytics = $res2->json();
                if ($analytics['success'] ?? false) {
                    $kpis = $analytics['data']['summary_kpis'] ?? [];
                    $costoIngredientes = (float) ($kpis['total_cost'] ?? 0);

                    $data['pnl']['costo_ventas']['costo_ingredientes'] = $costoIngredientes;
                }
            }
        } catch (\Exception $e) {
            // Silently fail
        }

        // Nómina: solo centro ruta11, excluyendo dueño
        try {
            $mes = now()->format('Y-m');
            $raw = $this->nominaService->getResumen($mes);

            $totalNomina = collect($raw['ruta11']['personal'] ?? [])
                ->filter(fn ($e) => ! str_contains($e['personal']->rol ?? '', 'dueño'))
                ->sum(fn ($e) => $e['liquidacion']['sueldo_base']);

            $data['nomina_mes'] = $totalNomina;
            $data['pnl']['gastos_operacion']['nomina_ruta11'] = $totalNomina;
        } catch (\Exception $e) {
            // Silently fail
        }

        // Calculate derived values
        $ventas = $data['pnl']['ingresos']['ventas_netas'];
        $cogs = $data['pnl']['costo_ventas']['costo_ingredientes'];
        $nomina = $data['pnl']['gastos_operacion']['nomina_ruta11'];

        $margenBruto = $ventas - $cogs;
        $data['pnl']['costo_ventas']['margen_bruto'] = $margenBruto;
        $data['pnl']['costo_ventas']['margen_bruto_pct'] = $ventas > 0
            ? round(($margenBruto / $ventas) * 100, 1)
            : 0;

        $totalOpex = $nomina;
        $data['pnl']['gastos_operacion']['total_opex'] = $totalOpex;

        $resultadoNeto = $margenBruto - $totalOpex;
        $data['pnl']['resultado']['resultado_neto'] = $resultadoNeto;
        $data['pnl']['resultado']['resultado_neto_pct'] = $ventas > 0
            ? round(($resultadoNeto / $ventas) * 100, 1)
            : 0;

        $data['resultado_bruto'] = $resultadoNeto;

        return response()->json(['success' => true, 'data' => $data]);
    }
}
