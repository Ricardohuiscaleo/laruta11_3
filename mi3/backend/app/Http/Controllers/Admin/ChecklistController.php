<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Checklist\AITrainingService;
use App\Services\Checklist\AttendanceService;
use App\Services\Checklist\ChecklistService;
use App\Services\Checklist\PhotoAnalysisService;
use App\Models\ChecklistAiPrompt;
use App\Models\ChecklistAiTask;
use App\Models\ChecklistAiTraining;
use App\Models\ChecklistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistService $checklistService,
        private readonly AttendanceService $attendanceService,
        private readonly AITrainingService $aiTrainingService,
        private readonly PhotoAnalysisService $photoAnalysisService,
    ) {}

    /**
     * GET /api/v1/admin/checklists
     */
    public function index(Request $request): JsonResponse
    {
        $fecha = $request->query('fecha', now()->format('Y-m-d'));
        $status = $request->query('status');
        $checklists = $this->checklistService->getChecklistsAdmin($fecha, $status);

        return response()->json(['success' => true, 'data' => $checklists]);
    }

    /**
     * GET /api/v1/admin/checklists/{id}
     */
    public function show(int $id): JsonResponse
    {
        $detail = $this->checklistService->getDetalleChecklist($id);
        return response()->json(['success' => true, 'data' => $detail]);
    }

    /**
     * GET /api/v1/admin/checklists/attendance
     */
    public function attendance(Request $request): JsonResponse
    {
        $mes = $request->query('mes', now()->format('Y-m'));
        $resumen = $this->attendanceService->getResumenAsistenciaAdmin($mes);
        return response()->json(['success' => true, 'data' => $resumen]);
    }

    /**
     * GET /api/v1/admin/checklists/ideas
     */
    public function ideas(): JsonResponse
    {
        $ideas = $this->checklistService->getIdeasMejora();
        return response()->json(['success' => true, 'data' => $ideas]);
    }

    /**
     * GET /api/v1/admin/checklists/ai-photos
     * List photos with AI evaluations, paginated, filterable by contexto.
     */
    public function aiPhotos(Request $request): JsonResponse
    {
        $contexto = $request->query('contexto');
        $perPage = (int) $request->query('per_page', 20);

        $query = ChecklistAiTraining::query()
            ->orderByDesc('created_at');

        if ($contexto) {
            $query->where('contexto', $contexto);
        }

        $photos = $query->paginate($perPage);

        // Also include legacy photos (checklist_items with photo_url but no ai_training record)
        // These are returned separately for the frontend to handle

        return response()->json([
            'success' => true,
            'data' => $photos->items(),
            'meta' => [
                'total' => $photos->total(),
                'per_page' => $photos->perPage(),
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
            ],
        ]);
    }

    /**
     * POST /api/v1/admin/checklists/ai-feedback
     * Register admin feedback on an AI evaluation.
     */
    public function aiFeedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'training_id' => 'required|integer|exists:checklist_ai_training,id',
            'feedback' => 'required|in:correct,incorrect',
            'admin_notes' => 'nullable|string',
            'admin_score' => 'nullable|integer|min:0|max:100',
        ]);

        $this->aiTrainingService->registrarFeedback(
            $data['training_id'],
            $data['feedback'],
            $data['admin_notes'] ?? null,
            $data['admin_score'] ?? null,
        );

        return response()->json(['success' => true]);
    }

    /**
     * POST /api/v1/admin/checklists/ai-test
     * Test a prompt with a photo (upload or existing URL).
     */
    public function aiTest(Request $request): JsonResponse
    {
        $request->validate([
            'photo' => 'required_without:photo_url|file|image|max:10240',
            'photo_url' => 'required_without:photo|string|url',
            'contexto' => 'required|string',
        ]);

        $contexto = $request->input('contexto');

        try {
            if ($request->hasFile('photo')) {
                $url = $this->photoAnalysisService->subirFotoS3($request->file('photo'));
            } else {
                $url = $request->input('photo_url');
            }

            $result = $this->photoAnalysisService->analizarConIA($url, $contexto);
            $promptUsed = $this->photoAnalysisService->getPromptForContext($contexto);

            return response()->json([
                'success' => true,
                'data' => [
                    'score' => $result['score'],
                    'observations' => $result['observations'],
                    'prompt_used' => $promptUsed,
                    'photo_url' => $url,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al analizar la foto: ' . $e->getMessage(),
            ], 502);
        }
    }

    /**
     * GET /api/v1/admin/checklists/ai-prompts
     * List all prompts with stats.
     */
    public function aiPrompts(): JsonResponse
    {
        $prompts = ChecklistAiPrompt::orderBy('contexto')
            ->orderByDesc('prompt_version')
            ->get()
            ->groupBy('contexto')
            ->map(function ($versions, $contexto) {
                $active = $versions->firstWhere('is_active', true);
                $precision = $this->aiTrainingService->calcularPrecision($contexto);
                $correctionsCount = ChecklistAiTraining::where('contexto', $contexto)
                    ->where('admin_feedback', 'incorrect')
                    ->count();
                $candidate = $versions->where('is_active', false)->sortByDesc('prompt_version')->first();

                return [
                    'contexto' => $contexto,
                    'active' => $active,
                    'versions' => $versions->values(),
                    'precision' => $precision,
                    'needs_review' => $precision < 70,
                    'corrections_count' => $correctionsCount,
                    'candidate' => $candidate,
                ];
            })
            ->values();

        return response()->json(['success' => true, 'data' => $prompts]);
    }

    /**
     * PUT /api/v1/admin/checklists/ai-prompts/{id}
     * Edit a prompt (creates new version).
     */
    public function aiPromptsUpdate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'prompt_base' => 'required|string|min:50',
        ]);

        $prompt = ChecklistAiPrompt::findOrFail($id);
        $newPrompt = $this->aiTrainingService->editarPrompt($prompt->contexto, $data['prompt_base']);

        return response()->json(['success' => true, 'data' => $newPrompt]);
    }

    /**
     * POST /api/v1/admin/checklists/ai-prompts/{id}/activate
     * Activate a prompt version.
     */
    public function aiPromptsActivate(int $id): JsonResponse
    {
        $prompt = $this->aiTrainingService->activarPrompt($id);
        return response()->json(['success' => true, 'data' => $prompt]);
    }

    /**
     * POST /api/v1/admin/checklists/ai-prompts/{id}/generate-candidate
     * Generate a candidate prompt using AI.
     */
    public function aiPromptsGenerateCandidate(int $id): JsonResponse
    {
        $prompt = ChecklistAiPrompt::findOrFail($id);

        try {
            $candidate = $this->aiTrainingService->generarPromptCandidato($prompt->contexto);
            return response()->json(['success' => true, 'data' => $candidate]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 502);
        }
    }

    /**
     * GET /api/v1/admin/checklists/ai-tasks
     * List AI tasks with filters and summary.
     */
    public function aiTasks(Request $request): JsonResponse
    {
        $contexto = $request->query('contexto');
        $status = $request->query('status');

        $query = ChecklistAiTask::orderByDesc('created_at');

        if ($contexto) {
            $query->where('contexto', $contexto);
        }
        if ($status) {
            $query->where('status', $status);
        }

        $tasks = $query->get();
        $summary = $this->aiTrainingService->getResumenTareas($contexto);

        return response()->json([
            'success' => true,
            'data' => $tasks,
            'summary' => $summary,
        ]);
    }
}
