<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Worker\ShiftSwapRequest;
use App\Services\Shift\ShiftSwapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftSwapController extends Controller
{
    public function __construct(
        private readonly ShiftSwapService $shiftSwapService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        return response()->json([
            'success' => true,
            'data' => $this->shiftSwapService->getSolicitudesForPersonal($personal->id),
        ]);
    }

    public function store(ShiftSwapRequest $request): JsonResponse
    {
        $personal = $request->get('personal');

        $solicitud = $this->shiftSwapService->crearSolicitud(
            $personal->id,
            $request->input('compañero_id'),
            $request->input('fecha_turno'),
            $request->input('motivo'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $solicitud->id,
                'estado' => $solicitud->estado,
            ],
        ], 201);
    }

    public function companions(Request $request): JsonResponse
    {
        $personal = $request->get('personal');

        $companions = $this->shiftSwapService->getCompañerosDisponibles($personal)
            ->map(fn($p) => ['id' => $p->id, 'nombre' => $p->nombre]);

        return response()->json([
            'success' => true,
            'data' => $companions,
        ]);
    }
}
