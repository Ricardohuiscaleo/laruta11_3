<?php

namespace App\Services\Checklist;

use App\Models\ChecklistAiPrompt;
use App\Models\ChecklistAiTask;
use App\Models\ChecklistAiTraining;
use App\Models\ChecklistItem;
use App\Models\Personal;
use App\Services\Notification\PushNotificationService;
use App\Services\Notification\TelegramService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AITrainingService
{
    /**
     * Register an AI evaluation as training data.
     */
    public function registrarEvaluacion(ChecklistItem $item, string $contexto, string $promptUsed): ChecklistAiTraining
    {
        return ChecklistAiTraining::create([
            'checklist_item_id' => $item->id,
            'photo_url' => $item->photo_url ?? '',
            'contexto' => $contexto,
            'ai_score' => $item->ai_score,
            'ai_observations' => $item->ai_observations,
            'prompt_used' => $promptUsed,
        ]);
    }

    /**
     * Register admin feedback on a training record.
     */
    public function registrarFeedback(int $trainingId, string $feedback, ?string $notes = null, ?int $adminScore = null): void
    {
        $training = ChecklistAiTraining::findOrFail($trainingId);

        $training->update([
            'admin_feedback' => $feedback,
            'admin_notes' => $notes,
            'admin_score' => $adminScore,
        ]);

        // Check if auto-generation threshold reached (10+ corrections for this context)
        if ($feedback === 'incorrect') {
            $this->checkAutoGenerationThreshold($training->contexto);
        }
    }

    /**
     * Get last N incorrect feedback entries for a context.
     */
    public function getCorreccionesPrevias(string $contexto, int $limit = 5): Collection
    {
        return ChecklistAiTraining::where('contexto', $contexto)
            ->where('admin_feedback', 'incorrect')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Calculate AI precision for a context.
     * Returns (correct / total_with_feedback) * 100.
     */
    public function calcularPrecision(string $contexto): float
    {
        $totalWithFeedback = ChecklistAiTraining::where('contexto', $contexto)
            ->whereNotNull('admin_feedback')
            ->count();

        if ($totalWithFeedback === 0) {
            return 100.0; // No feedback yet = assume perfect
        }

        $correct = ChecklistAiTraining::where('contexto', $contexto)
            ->where('admin_feedback', 'correct')
            ->count();

        return round(($correct / $totalWithFeedback) * 100, 2);
    }

    /**
     * Generate a candidate prompt using AI based on accumulated corrections.
     */
    public function generarPromptCandidato(string $contexto): ChecklistAiPrompt
    {
        $activePrompt = ChecklistAiPrompt::where('contexto', $contexto)
            ->where('is_active', true)
            ->first();

        if (!$activePrompt) {
            throw new \InvalidArgumentException("No hay prompt activo para el contexto: {$contexto}");
        }

        $corrections = $this->getCorreccionesPrevias($contexto, 20);

        $correctionText = $corrections->map(function ($c, $i) {
            $num = $i + 1;
            return "Corrección {$num}: IA dijo \"{$c->ai_observations}\" (score: {$c->ai_score}). Admin corrigió: \"{$c->admin_notes}\" (score correcto: {$c->admin_score}).";
        })->implode("\n");

        // Use Bedrock to generate improved prompt
        try {
            $client = new \Aws\BedrockRuntime\BedrockRuntimeClient([
                'region' => config('services.bedrock.region', env('AWS_DEFAULT_REGION', 'us-east-1')),
                'version' => 'latest',
                'credentials' => [
                    'key' => config('services.bedrock.key', env('AWS_ACCESS_KEY_ID')),
                    'secret' => config('services.bedrock.secret', env('AWS_SECRET_ACCESS_KEY')),
                ],
                'http' => ['timeout' => 30, 'connect_timeout' => 5],
            ]);

            $response = $client->invokeModel([
                'modelId' => 'amazon.nova-pro-v1:0',
                'contentType' => 'application/json',
                'accept' => 'application/json',
                'body' => json_encode([
                    'messages' => [[
                        'role' => 'user',
                        'content' => [[
                            'text' => "Eres un experto en prompt engineering. Reescribe este prompt incorporando las correcciones del administrador para mejorar la precisión.\n\nPROMPT ACTUAL:\n{$activePrompt->prompt_base}\n\nCORRECCIONES ACUMULADAS:\n{$correctionText}\n\nReescribe el prompt mejorado manteniendo el mismo formato JSON de respuesta. Solo devuelve el prompt mejorado, sin explicaciones.",
                        ]],
                    ]],
                    'inferenceConfig' => ['max_new_tokens' => 2000, 'temperature' => 0.3],
                ]),
            ]);

            $body = json_decode($response['body'], true);
            $newPromptText = $body['output']['message']['content'][0]['text'] ?? $activePrompt->prompt_base;
        } catch (\Throwable $e) {
            Log::error('Failed to generate candidate prompt: ' . $e->getMessage());
            throw new \RuntimeException('Error al generar prompt candidato');
        }

        // Get next version number
        $maxVersion = ChecklistAiPrompt::where('contexto', $contexto)->max('prompt_version') ?? 0;

        return ChecklistAiPrompt::create([
            'contexto' => $contexto,
            'prompt_base' => $newPromptText,
            'prompt_version' => $maxVersion + 1,
            'is_active' => false, // Candidate — not active until admin activates
        ]);
    }

    /**
     * Get summary of AI tasks, optionally filtered by context.
     */
    public function getResumenTareas(?string $contexto = null): array
    {
        $query = ChecklistAiTask::query();

        if ($contexto) {
            $query->where('contexto', $contexto);
        }

        return [
            'activos' => (clone $query)->whereIn('status', ['pendiente', 'no_mejorado'])->count(),
            'mejorados' => (clone $query)->where('status', 'mejorado')->count(),
            'escalados' => (clone $query)->where('status', 'escalado')->count(),
        ];
    }

    /**
     * Prompt versioning: edit creates new version, deactivates previous.
     * Ensures exactly one active version per context.
     */
    public function editarPrompt(string $contexto, string $newPromptBase): ChecklistAiPrompt
    {
        // Deactivate all current active versions for this context
        ChecklistAiPrompt::where('contexto', $contexto)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        // Get next version number
        $maxVersion = ChecklistAiPrompt::where('contexto', $contexto)->max('prompt_version') ?? 0;

        return ChecklistAiPrompt::create([
            'contexto' => $contexto,
            'prompt_base' => $newPromptBase,
            'prompt_version' => $maxVersion + 1,
            'is_active' => true,
        ]);
    }

    /**
     * Activate a specific prompt version (deactivate all others for that context).
     */
    public function activarPrompt(int $promptId): ChecklistAiPrompt
    {
        $prompt = ChecklistAiPrompt::findOrFail($promptId);

        // Deactivate all for this context
        ChecklistAiPrompt::where('contexto', $prompt->contexto)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        $prompt->update(['is_active' => true]);

        return $prompt->refresh();
    }

    /**
     * Escalation logic: when veces_detectado >= 3, set status='escalado' and notify.
     */
    public function escalarSiNecesario(ChecklistAiTask $task): void
    {
        if ($task->veces_detectado < 3 || $task->status === 'escalado') {
            return;
        }

        $task->update(['status' => 'escalado']);

        try {
            $telegram = app(TelegramService::class);
            $telegram->sendToLaruta11("🚨 Problema recurrente ({$task->contexto}): {$task->problema_detectado} — Detectado {$task->veces_detectado} veces");

            $pushService = app(PushNotificationService::class);
            $admins = Personal::where('activo', true)
                ->where(function ($q) {
                    $q->where('rol', 'LIKE', '%administrador%')
                      ->orWhere('rol', 'LIKE', '%dueño%');
                })
                ->get();

            foreach ($admins as $admin) {
                $pushService->enviar(
                    $admin->id,
                    '🚨 Problema recurrente',
                    "{$task->contexto}: {$task->problema_detectado}",
                    '/admin/checklists',
                    'high'
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Escalation notification failed: ' . $e->getMessage());
        }
    }

    /**
     * Check if auto-generation threshold is reached (10+ corrections for a context).
     */
    protected function checkAutoGenerationThreshold(string $contexto): void
    {
        $incorrectCount = ChecklistAiTraining::where('contexto', $contexto)
            ->where('admin_feedback', 'incorrect')
            ->count();

        if ($incorrectCount < 10) {
            return;
        }

        // Check if there's already an inactive candidate for this context
        $existingCandidate = ChecklistAiPrompt::where('contexto', $contexto)
            ->where('is_active', false)
            ->orderByDesc('prompt_version')
            ->first();

        // Only auto-generate if no candidate exists yet or the last one was activated
        if ($existingCandidate) {
            return;
        }

        try {
            $this->generarPromptCandidato($contexto);
            Log::info("Auto-generated candidate prompt for context: {$contexto} (corrections: {$incorrectCount})");
        } catch (\Throwable $e) {
            Log::warning("Failed to auto-generate candidate prompt for {$contexto}: " . $e->getMessage());
        }
    }
}
