<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Models\Personal;
use App\Models\Turno;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplacementController extends Controller
{
    /**
     * GET /api/v1/worker/replacements?mes=YYYY-MM
     *
     * Returns replacements done and received for the given month, plus summary.
     */
    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $mes = $request->query('mes', now()->format('Y-m'));

        $inicioMes = Carbon::parse($mes . '-01')->startOfDay();
        $finMes = $inicioMes->copy()->endOfMonth()->endOfDay();

        $tiposReemplazo = ['reemplazo', 'reemplazo_seguridad'];

        // Realizados: worker is the reemplazante
        $realizados = Turno::where('reemplazado_por', $personal->id)
            ->whereIn('tipo', $tiposReemplazo)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get()
            ->map(function (Turno $turno) {
                $titular = Personal::find($turno->personal_id);
                return [
                    'fecha' => $turno->fecha->format('Y-m-d'),
                    'titular' => $titular?->nombre ?? 'Desconocido',
                    'monto' => (int) ($turno->monto_reemplazo ?? 20000),
                    'pago_por' => $turno->pago_por ?? 'empresa',
                ];
            });

        // Recibidos: worker is the titular being replaced
        $recibidos = Turno::where('personal_id', $personal->id)
            ->whereIn('tipo', $tiposReemplazo)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->get()
            ->map(function (Turno $turno) {
                $reemplazante = Personal::find($turno->reemplazado_por);
                return [
                    'fecha' => $turno->fecha->format('Y-m-d'),
                    'reemplazante' => $reemplazante?->nombre ?? 'Desconocido',
                    'monto' => (int) ($turno->monto_reemplazo ?? 20000),
                    'pago_por' => $turno->pago_por ?? 'empresa',
                ];
            });

        $totalGanado = $realizados->sum('monto');
        $totalDescontado = $recibidos->sum('monto');

        return response()->json([
            'success' => true,
            'data' => [
                'realizados' => $realizados->values(),
                'recibidos' => $recibidos->values(),
                'resumen' => [
                    'total_ganado' => $totalGanado,
                    'total_descontado' => $totalDescontado,
                    'balance' => $totalGanado - $totalDescontado,
                ],
            ],
        ]);
    }
}
