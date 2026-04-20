<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Compra\AiPromptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiPromptController extends Controller
{
    public function __construct(
        private AiPromptService $promptService,
    ) {}

    /**
     * GET /compras/ai-prompts — List all prompts.
     */
    public function index(): JsonResponse
    {
        $prompts = $this->promptService->getAll();

        return response()->json([
            'success' => true,
            'data' => $prompts,
        ]);
    }

    /**
     * GET /compras/ai-prompts/{id} — Single prompt with version history.
     */
    public function show(int $id): JsonResponse
    {
        $prompt = \App\Models\AiPrompt::findOrFail($id);
        $history = $this->promptService->getHistory($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($prompt->toArray(), [
                'versions' => $history->toArray(),
            ]),
        ]);
    }

    /**
     * PUT /compras/ai-prompts/{id} — Update prompt text.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'prompt_text' => 'required|string|min:1',
            'description' => 'nullable|string',
        ]);

        $prompt = $this->promptService->update(
            $id,
            $validated['prompt_text'],
            $validated['description'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $prompt,
        ]);
    }

    /**
     * POST /compras/ai-prompts/{id}/revert/{versionId} — Revert to a previous version.
     */
    public function revert(int $id, int $versionId): JsonResponse
    {
        $prompt = $this->promptService->revertToVersion($id, $versionId);

        return response()->json([
            'success' => true,
            'data' => $prompt,
        ]);
    }
}
