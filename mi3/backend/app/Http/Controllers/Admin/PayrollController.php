<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminDataUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\AjusteSueldo;
use App\Models\NominaSnapshot;
use App\Models\PagoNomina;
use App\Models\Personal;
use App\Models\PresupuestoNomina;
use App\Services\Email\GmailService;
use App\Services\Payroll\LiquidacionService;
use App\Services\Payroll\NominaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $raw = $this->nominaService->getResumen($mes);
        $mesDate = $mes . '-01';

        $result = [];

        foreach (['ruta11', 'seguridad'] as $centro) {
            $workers = [];

            foreach ($raw[$centro]['personal'] ?? [] as $entry) {
                $p = $entry['personal'];
                $liq = $entry['liquidacion'];
                $pid = $p->id;

                // Get detailed adjustments for this worker in this month
                $ajustes = AjusteSueldo::with('categoria')
                    ->where('personal_id', $pid)
                    ->where('mes', $mesDate)
                    ->get()
                    ->map(fn($a) => [
                        'id' => $a->id,
                        'monto' => $a->monto,
                        'concepto' => $a->concepto,
                        'categoria' => $a->categoria?->nombre ?? '',
                        'categoria_slug' => $a->categoria?->slug ?? '',
                        'notas' => $a->notas,
                    ])
                    ->toArray();

                // Only include adjustments in ruta11 context (avoid double counting)
                $hasRuta11Role = preg_match('/administrador|cajero|planchero/', $p->rol ?? '');
                $includeAjustes = $centro === 'ruta11' || ($centro === 'seguridad' && !$hasRuta11Role);

                // Separate descuentos (negative adjustments) from bonos (positive)
                $descuentos = [];
                $bonos = [];
                if ($includeAjustes) {
                    foreach ($ajustes as $a) {
                        if ($a['monto'] < 0) {
                            $descuentos[] = $a;
                        } else {
                            $bonos[] = $a;
                        }
                    }
                }

                // R11 credit pending — only applies to ruta11 cost center
                $creditoPendiente = 0;
                if ($centro === 'ruta11' && $p->user_id) {
                    $usuario = DB::table('usuarios')
                        ->where('id', $p->user_id)
                        ->where('es_credito_r11', 1)
                        ->where('credito_r11_usado', '>', 0)
                        ->first();

                    if ($usuario) {
                        $yaDescontado = DB::table('ajustes_sueldo')
                            ->where('personal_id', $pid)
                            ->where('mes', $mesDate)
                            ->where('categoria_id', function ($q) {
                                $q->select('id')
                                    ->from('ajustes_categorias')
                                    ->where('slug', 'descuento_credito_r11')
                                    ->limit(1);
                            })
                            ->exists();

                        $creditoPendiente = $yaDescontado ? 0 : (float) $usuario->credito_r11_usado;
                    }
                }

                $totalDescuentos = collect($descuentos)->sum('monto'); // negative
                $totalBonos = collect($bonos)->sum('monto'); // positive
                $totalAjustes = $includeAjustes ? ($totalDescuentos + $totalBonos) : 0;

                $totalAPagar = (int) round(
                    $liq['sueldo_base']
                    + ($liq['total_reemplazando'] ?? 0)
                    - ($liq['total_reemplazados'] ?? 0)
                    + $totalAjustes
                    - $creditoPendiente
                );

                $workers[] = [
                    'personal_id' => $pid,
                    'nombre' => $p->nombre,
                    'rol' => $p->rol,
                    'sueldo_base' => (int) round($liq['sueldo_base']),
                    'dias_trabajados' => $liq['dias_trabajados'],
                    'reemplazos_hechos' => $liq['reemplazos_hechos'],
                    'total_reemplazando' => (int) round($liq['total_reemplazando'] ?? 0),
                    'total_reemplazado' => (int) round($liq['total_reemplazados'] ?? 0),
                    'reemplazos_realizados' => $liq['reemplazos_realizados'] ?? [],
                    'reemplazos_recibidos' => $liq['reemplazos_recibidos'] ?? [],
                    'descuentos' => $descuentos,
                    'bonos' => $bonos,
                    'total_descuentos' => (int) round($totalDescuentos),
                    'total_bonos' => (int) round($totalBonos),
                    'credito_r11_pendiente' => $creditoPendiente,
                    'total_a_pagar' => $totalAPagar,
                ];
            }

            // Summary for this cost center
            $totalSueldosBase = collect($workers)
                ->filter(fn($w) => !str_contains($w['rol'] ?? '', 'dueño'))
                ->sum('sueldo_base');
            $totalDescuentosCentro = collect($workers)->sum('total_descuentos');
            $totalCreditosCentro = collect($workers)->sum('credito_r11_pendiente');
            $totalAPagarCentro = collect($workers)
                ->filter(fn($w) => !str_contains($w['rol'] ?? '', 'dueño'))
                ->sum('total_a_pagar');
            $totalPagado = collect($raw[$centro]['pagos'] ?? [])->sum('monto');
            $presupuesto = $raw[$centro]['presupuesto'] ?? 0;

            $result[$centro] = [
                'workers' => $workers,
                'summary' => [
                    'presupuesto' => (int) round($presupuesto),
                    'total_sueldos_base' => (int) round($totalSueldosBase),
                    'total_descuentos' => (int) round($totalDescuentosCentro),
                    'total_creditos' => (int) round($totalCreditosCentro),
                    'total_a_pagar' => (int) round($totalAPagarCentro),
                    'total_pagado' => (int) round($totalPagado),
                    'diferencia' => (int) round($presupuesto - $totalAPagarCentro),
                ],
                'pagos' => collect($raw[$centro]['pagos'] ?? [])->map(fn($p) => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'monto' => $p->monto,
                    'notas' => $p->notas,
                    'es_externo' => $p->es_externo ?? false,
                ])->toArray(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
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

        try {
            broadcast(new AdminDataUpdatedEvent('nomina', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast nomina payment: ' . $e->getMessage());
        }

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

        try {
            broadcast(new AdminDataUpdatedEvent('nomina', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast nomina budget: ' . $e->getMessage());
        }

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

    /**
     * Generate a public snapshot of the current payroll data.
     * POST /admin/payroll/snapshot
     */
    public function generateSnapshot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mes' => 'required|date_format:Y-m',
        ]);

        $mes = $data['mes'];

        // Reuse the index() logic to get the full payroll data
        $fakeRequest = Request::create('/admin/payroll', 'GET', ['mes' => $mes]);
        $response = $this->index($fakeRequest);
        $payrollData = json_decode($response->getContent(), true)['data'] ?? [];

        $snapshot = NominaSnapshot::create([
            'mes' => $mes,
            'data' => $payrollData,
        ]);

        return response()->json([
            'success' => true,
            'token' => $snapshot->token,
            'url' => "https://mi.laruta11.cl/nomina/{$snapshot->token}",
        ]);
    }

    /**
     * Show a public payroll snapshot (no auth required).
     * GET /nomina/{token}
     */
    public static function showSnapshot(string $token): JsonResponse
    {
        $snapshot = NominaSnapshot::where('token', $token)->firstOrFail();

        return response()->json([
            'success' => true,
            'mes' => $snapshot->mes,
            'data' => $snapshot->data,
            'created_at' => $snapshot->created_at?->toIso8601String(),
        ]);
    }
}
