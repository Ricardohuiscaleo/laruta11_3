<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
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

        // Nómina from local DB (sueldos base de personal activo)
        try {
            $personal = DB::table('personal')->where('activo', 1)->get();
            $totalNomina = 0;
            foreach ($personal as $p) {
                $roles = explode(',', $p->rol ?? '');
                foreach ($roles as $rol) {
                    $rol = trim($rol);
                    $field = match($rol) {
                        'cajero' => 'sueldo_base_cajero',
                        'planchero' => 'sueldo_base_planchero',
                        'administrador' => 'sueldo_base_admin',
                        'seguridad' => 'sueldo_base_seguridad',
                        default => null,
                    };
                    if ($field && isset($p->$field)) {
                        $totalNomina += (float) $p->$field;
                    }
                }
            }
            $data['nomina_mes'] = $totalNomina;
        } catch (\Exception $e) {}

        $data['resultado_bruto'] = $data['ventas_mes'] - $data['compras_mes'] - $data['nomina_mes'];

        return response()->json(['success' => true, 'data' => $data]);
    }
}
