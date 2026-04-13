<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreShiftRequest;
use App\Models\Turno;
use App\Services\Shift\ShiftService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));
        $turnos = $this->shiftService->getShiftsForMonth($mes);

        return response()->json([
            'success' => true,
            'data' => [
                'mes' => $mes,
                'turnos' => $turnos,
            ],
        ]);
    }

    public function store(StoreShiftRequest $request): JsonResponse
    {
        $data = $request->validated();
        $fechaInicio = Carbon::parse($data['fecha']);
        $fechaFin = isset($data['fecha_fin']) ? Carbon::parse($data['fecha_fin']) : $fechaInicio->copy();

        try {
            $creados = [];
            $current = $fechaInicio->copy();

            while ($current <= $fechaFin) {
                $creados[] = Turno::create([
                    'personal_id' => $data['personal_id'],
                    'fecha' => $current->format('Y-m-d'),
                    'tipo' => $data['tipo'],
                    'reemplazado_por' => $data['reemplazado_por'] ?? null,
                    'monto_reemplazo' => $data['monto_reemplazo'] ?? null,
                    'pago_por' => $data['pago_por'] ?? null,
                ]);
                $current->addDay();
            }

            return response()->json([
                'success' => true,
                'data' => $creados,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear turno: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        $turno = Turno::findOrFail($id);
        $turno->delete();

        return response()->json(['success' => true]);
    }
}
