<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Checklist\AttendanceService;
use App\Services\Checklist\ChecklistService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly AttendanceService $attendanceService,
    ) {}

    /**
     * GET /api/v1/admin/checklists
     * Lista checklists con filtro fecha y status.
     */
    public function index(Request $request): JsonResponse
    {
        $fecha = $request->query('fecha', now()->format('Y-m-d'));
        $status = $request->query('status');

        $checklists = $this->checklistService->getChecklistsAdmin($fecha, $status);

        return response()->json([
            'success' => true,
            'data' => $checklists,
        ]);
    }

    /**
     * GET /api/v1/admin/checklists/{id}
     * Detalle checklist con ítems y resultados IA.
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->checklistService->getDetalleChecklist($id);

        return response()->json([
            'success' => true,
            'data' => $detail,
        ]);
    }

    /**
     * GET /api/v1/admin/checklists/attendance
     * Resumen asistencia mensual por trabajador.
     */
    public function attendance(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));

        $resumen = $this->attendanceService->getResumenAsistenciaAdmin($mes);

        return response()->json([
            'success' => true,
            'data' => $resumen,
        ]);
    }

    /**
     * GET /api/v1/admin/checklists/ideas
     * Ideas de mejora de virtuales ordenadas por fecha desc.
     */
    public function ideas(): JsonResponse
    {
        $ideas = $this->checklistService->getIdeasMejora();

        return response()->json([
            'success' => true,
            'data' => $ideas,
        ]);
    }
}
