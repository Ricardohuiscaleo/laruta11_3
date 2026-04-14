<?php

namespace App\Http\Controllers\Worker;

use App\Http\Controllers\Controller;
use App\Services\Checklist\ChecklistService;
use App\Services\Checklist\PhotoAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly PhotoAnalysisService $photoAnalysisService,
    ) {}

    /**
     * GET /api/v1/worker/checklists
     * Checklists pendientes del día filtrados por rol del trabajador.
     */
    public function index(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $fecha = $request->query('fecha', now()->format('Y-m-d'));

        $checklists = $this->checklistService->getChecklistsPendientes($personal->id, $fecha);

        // On-demand creation: if worker has a shift today but no checklists, create them now
        // This handles cases where the daily cron hasn't run yet or was missed
        if ($checklists->isEmpty()) {
            $hasTurno = \App\Models\Turno::whereDate('fecha', $fecha)
                ->where(function ($q) use ($personal) {
                    $q->where('personal_id', $personal->id)
                      ->orWhere('reemplazado_por', $personal->id);
                })->exists();

            if ($hasTurno) {
                $this->checklistService->crearChecklistsDiarios($fecha);
                $checklists = $this->checklistService->getChecklistsPendientes($personal->id, $fecha);
            }
        }

        // For cash_verification items, refresh cash_expected with current balance (dynamic until verified)
        foreach ($checklists as $checklist) {
            foreach ($checklist->items as $item) {
                if ($item->item_type === 'cash_verification' && !$item->is_completed) {
                    $saldoEsperado = \Illuminate\Support\Facades\DB::table('caja_movimientos')
                        ->orderByDesc('id')
                        ->value('saldo_nuevo') ?? 0;
                    $item->cash_expected = $saldoEsperado;
                    $item->saveQuietly(); // persist to DB so verify-cash uses the latest
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $checklists,
        ]);
    }

    /**
     * POST /api/v1/worker/checklists/{id}/items/{itemId}/complete
     * Marcar ítem como completado.
     */
    public function completeItem(Request $request, int $id, int $itemId): JsonResponse
    {
        $personal = $request->get('personal');

        try {
            $result = $this->checklistService->marcarItemCompletado($itemId, $personal->id);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'permiso') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * POST /api/v1/worker/checklists/{id}/items/{itemId}/verify-cash
     * Verificar caja (confirmar o reportar discrepancia).
     */
    public function verifyCash(Request $request, int $id, int $itemId): JsonResponse
    {
        $personal = $request->get('personal');

        $data = $request->validate([
            'confirmed' => 'nullable|boolean',
            'actual_amount' => 'required|numeric|min:0',
        ]);

        try {
            $result = $this->checklistService->verificarCaja(
                $itemId,
                $personal->id,
                $data['confirmed'],
                $data['actual_amount'] ?? null,
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'permiso') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * POST /api/v1/worker/checklists/{id}/items/{itemId}/photo
     * Subir foto para un ítem (multipart/form-data).
     */
    public function uploadPhoto(Request $request, int $id, int $itemId): JsonResponse
    {
        $personal = $request->get('personal');

        $request->validate([
            'photo' => 'required|file|image|max:10240',
            'contexto' => 'nullable|string',
        ]);

        try {
            $contexto = $request->input('contexto', 'interior_apertura');
            $result = $this->photoAnalysisService->subirYAnalizar(
                $request->file('photo'),
                $itemId,
                $contexto,
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al subir la foto. Intente nuevamente.',
            ], 502);
        }
    }

    /**
     * POST /api/v1/worker/checklists/{id}/complete
     * Completar checklist.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $personal = $request->get('personal');

        try {
            $checklist = $this->checklistService->completarChecklist($id, $personal->id);

            return response()->json([
                'success' => true,
                'data' => $checklist,
            ]);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'permiso') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * GET /api/v1/worker/checklists/virtual
     * Checklist virtual disponible para el trabajador.
     */
    public function virtual(Request $request): JsonResponse
    {
        $personal = $request->get('personal');
        $fecha = $request->query('fecha', now()->format('Y-m-d'));

        $virtual = $this->checklistService->getChecklistVirtualDisponible($personal->id, $fecha);

        return response()->json([
            'success' => true,
            'data' => $virtual,
        ]);
    }

    /**
     * POST /api/v1/worker/checklists/virtual/{id}/complete
     * Completar checklist virtual con idea de mejora.
     */
    public function completeVirtual(Request $request, int $id): JsonResponse
    {
        $personal = $request->get('personal');

        $data = $request->validate([
            'idea_mejora' => 'required|string|min:20',
        ]);

        try {
            $virtual = $this->checklistService->completarChecklistVirtual(
                $id,
                $personal->id,
                $data['idea_mejora'],
            );

            return response()->json([
                'success' => true,
                'data' => $virtual,
            ]);
        } catch (\InvalidArgumentException $e) {
            $statusCode = str_contains($e->getMessage(), 'permiso') ? 403 : 422;

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $statusCode);
        }
    }
}
