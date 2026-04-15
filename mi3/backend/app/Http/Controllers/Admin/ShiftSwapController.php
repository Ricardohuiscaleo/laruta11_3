<?php

namespace App\Http\Controllers\Admin;

use App\Events\AdminDataUpdatedEvent;
use App\Http\Controllers\Controller;
use App\Models\SolicitudCambioTurno;
use App\Services\Shift\ShiftSwapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiftSwapController extends Controller
{
    public function __construct(
        private readonly ShiftSwapService $shiftSwapService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->shiftSwapService->getSolicitudesPendientes(),
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $solicitud = SolicitudCambioTurno::findOrFail($id);
        $personal = $request->get('personal');

        $this->shiftSwapService->aprobar($solicitud, $personal->id);

        try {
            broadcast(new AdminDataUpdatedEvent('cambios', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast cambios approve: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $solicitud = SolicitudCambioTurno::findOrFail($id);
        $personal = $request->get('personal');

        $this->shiftSwapService->rechazar($solicitud, $personal->id);

        try {
            broadcast(new AdminDataUpdatedEvent('cambios', 'updated'));
        } catch (\Throwable $e) {
            Log::warning('Broadcast cambios reject: ' . $e->getMessage());
        }

        return response()->json(['success' => true]);
    }
}
