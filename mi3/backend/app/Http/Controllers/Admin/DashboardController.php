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
        ];

        // Ventas y compras from caja3 API
        try {
            $res = Http::timeout(5)->get('https://caja.laruta11.cl/api/get_dashboard_cards.php');
            if ($res->successful()) {
                $cards = $res->json();
                if ($cards['success'] ?? false) {
                    $data['ventas_mes'] = (float) ($cards['data']['ventas']['real'] ?? 0);
                    $data['compras_mes'] = (float) ($cards['data']['compras']['total_mes'] ?? 0);
                }
            }
        } catch (\Exception $e) {}

        // Nómina: solo centro ruta11, excluyendo dueño (misma lógica que PayrollController)
        try {
            $mes = now()->format('Y-m');
            $raw = $this->nominaService->getResumen($mes);

            $totalNomina = collect($raw['ruta11']['personal'] ?? [])
                ->filter(fn($e) => !str_contains($e['personal']->rol ?? '', 'dueño'))
                ->sum(fn($e) => $e['liquidacion']['sueldo_base']);

            $data['nomina_mes'] = $totalNomina;
        } catch (\Exception $e) {}

        $data['resultado_bruto'] = $data['ventas_mes'] - $data['compras_mes'] - $data['nomina_mes'];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
