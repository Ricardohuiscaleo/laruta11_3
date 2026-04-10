<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PagoNomina;
use App\Models\Personal;
use App\Models\PresupuestoNomina;
use App\Services\Email\GmailService;
use App\Services\Payroll\LiquidacionService;
use App\Services\Payroll\NominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        private readonly NominaService $nominaService,
        private readonly LiquidacionService $liquidacionService,
        private readonly GmailService $gmailService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));
        $resumen = $this->nominaService->getResumen($mes);

        return response()->json(['success' => true, 'data' => $resumen]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => 'required|date_format:Y-m',
            'personal_id' => 'nullable|integer|exists:personal,id',
            'nombre' => 'required|string|max:100',
            'monto' => 'required|numeric|min:0',
            'es_externo' => 'nullable|boolean',
            'notas' => 'nullable|string|max:500',
            'centro_costo' => 'required|in:ruta11,seguridad',
        ]);

        $data['mes'] = $data['mes'] . '-01';

        $pago = PagoNomina::create($data);

        return response()->json(['success' => true, 'data' => $pago], 201);
    }

    public function updateBudget(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => 'required|date_format:Y-m',
            'monto' => 'required|numeric|min:0',
            'centro_costo' => 'required|in:ruta11,seguridad',
        ]);

        $presupuesto = PresupuestoNomina::updateOrCreate(
            ['mes' => $data['mes'] . '-01', 'centro_costo' => $data['centro_costo']],
            ['monto' => $data['monto']],
        );

        return response()->json(['success' => true, 'data' => $presupuesto]);
    }

    public function sendLiquidacion(Request $request): JsonResponse
    {
        $data = $request->validate([
            'personal_id' => 'required|integer|exists:personal,id',
            'mes' => 'required|date_format:Y-m',
        ]);

        $personal = Personal::findOrFail($data['personal_id']);
        $liquidacion = [];

        $liqRuta11 = $this->liquidacionService->calcular($personal, $data['mes'], 'ruta11');
        if ($liqRuta11['sueldo_base'] > 0 || $liqRuta11['total'] != 0) {
            $liquidacion[] = $liqRuta11;
        }

        $liqSeguridad = $this->liquidacionService->calcular($personal, $data['mes'], 'seguridad');
        if ($liqSeguridad['sueldo_base'] > 0 || $liqSeguridad['total'] != 0) {
            $liquidacion[] = $liqSeguridad;
        }

        $sent = $this->gmailService->sendLiquidacionEmail($personal, $data['mes'], $liquidacion);

        return response()->json([
            'success' => $sent,
            'message' => $sent ? 'Liquidación enviada' : 'Error al enviar',
        ], $sent ? 200 : 500);
    }

    public function sendAll(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => 'required|date_format:Y-m',
        ]);

        $personal = Personal::where('activo', 1)->get();
        $enviados = 0;
        $errores = 0;

        foreach ($personal as $p) {
            $liquidacion = [];

            $liqRuta11 = $this->liquidacionService->calcular($p, $data['mes'], 'ruta11');
            if ($liqRuta11['sueldo_base'] > 0 || $liqRuta11['total'] != 0) {
                $liquidacion[] = $liqRuta11;
            }

            $liqSeguridad = $this->liquidacionService->calcular($p, $data['mes'], 'seguridad');
            if ($liqSeguridad['sueldo_base'] > 0 || $liqSeguridad['total'] != 0) {
                $liquidacion[] = $liqSeguridad;
            }

            if (empty($liquidacion)) {
                continue;
            }

            $sent = $this->gmailService->sendLiquidacionEmail($p, $data['mes'], $liquidacion);
            $sent ? $enviados++ : $errores++;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'enviados' => $enviados,
                'errores' => $errores,
                'total' => $enviados + $errores,
            ],
        ]);
    }
}
