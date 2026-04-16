<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Checklist;
use App\Models\Turno;
use App\Services\Checklist\ChecklistService;
use App\Services\Checklist\PhotoAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly PhotoAnalysisService $photoAnalysisService,
    ) {}

    /**
     * GET /api/v1/public/checklists/today
     * Checklists del día filtrados por rol (para caja3).
     */
    public function today(Request $request): JsonResponse
    {
        $request->validate([
            'rol' => 'required|in:cajero,planchero',
            'type' => 'nullable|in:apertura,cierre',
        ]);

        $rol = $request->query('rol');
        $type = $request->query('type');

        // Shift-day logic: between 00:00-04:00, the shift belongs to yesterday
        $chileNow = now('America/Santiago');
        $chileHour = (int) $chileNow->format('H');
        $fecha = $chileHour < 4
            ? $chileNow->copy()->subDay()->format('Y-m-d')
            : $chileNow->format('Y-m-d');

        $query = Checklist::with('items')
            ->where('rol', $rol)
            ->whereDate('scheduled_date', $fecha);

        if ($type) {
            $query->where('type', $type);
        }

        $checklists = $query->orderBy('type')->get();

        // On-demand creation if none exist for today
        if ($checklists->isEmpty()) {
            $this->checklistService->crearChecklistsDiarios($fecha);

            $checklists = Checklist::with('items')
                ->where('rol', $rol)
                ->whereDate('scheduled_date', $fecha);

            if ($type) {
                $checklists = $checklists->where('type', $type);
            }

            $checklists = $checklists->orderBy('type')->get();
        }

        // Refresh cash_expected for incomplete cash_verification items
        foreach ($checklists as $checklist) {
            foreach ($checklist->items as $item) {
                if ($item->item_type === 'cash_verification' && !$item->is_completed) {
                    $saldoEsperado = DB::table('caja_movimientos')
                        ->orderByDesc('id')
                        ->value('saldo_nuevo') ?? 0;
                    $item->cash_expected = $saldoEsperado;
                    $item->saveQuietly();
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $checklists,
        ]);
    }

    /**
     * POST /api/v1/public/checklists/{id}/items/{itemId}/complete
     * Marcar ítem como completado (identifica worker por checklist.personal_id).
     */
    public function completeItem(int $id, int $itemId): JsonResponse
    {
        $checklist = Checklist::findOrFail($id);

        try {
            $result = $this->checklistService->marcarItemCompletado($itemId, $checklist->personal_id);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * POST /api/v1/public/checklists/{id}/items/{itemId}/photo
     * Subir foto para un ítem (multipart/form-data).
     */
    public function uploadPhoto(Request $request, int $id, int $itemId): JsonResponse
    {
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
     * POST /api/v1/public/checklists/{id}/items/{itemId}/verify-cash
     * Verificar caja con monto real.
     */
    public function verifyCash(Request $request, int $id, int $itemId): JsonResponse
    {
        $checklist = Checklist::findOrFail($id);

        $data = $request->validate([
            'actual_amount' => 'required|numeric|min:0',
        ]);

        try {
            $result = $this->checklistService->verificarCaja(
                $itemId,
                $checklist->personal_id,
                true,
                $data['actual_amount'],
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
        }
    }

    /**
     * POST /api/v1/public/checklists/{id}/complete
     * Completar checklist entero.
     */
    public function complete(int $id): JsonResponse
    {
        $checklist = Checklist::findOrFail($id);

        try {
            $result = $this->checklistService->completarChecklist($id, $checklist->personal_id);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
