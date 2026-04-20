<?php

declare(strict_types=1);

namespace App\Services\Compra;

use App\Models\AiPrompt;
use App\Models\AiPromptVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AiPromptService
{
    /**
     * Get a prompt by slug and pipeline with cache-through pattern.
     * Replaces {key} placeholders with provided variables.
     *
     * @throws RuntimeException if prompt not found
     */
    public function getPrompt(string $slug, string $pipeline, array $variables = []): string
    {
        $cacheKey = "ai_prompts:{$slug}:{$pipeline}";

        $text = Cache::get($cacheKey);

        if ($text === null) {
            $prompt = AiPrompt::query()
                ->bySlug($slug)
                ->byPipeline($pipeline)
                ->active()
                ->first();

            if ($prompt === null) {
                throw new RuntimeException("AI prompt not found: {$slug}/{$pipeline}");
            }

            $text = $prompt->prompt_text;
            Cache::put($cacheKey, $text, 3600);
        }

        foreach ($variables as $key => $value) {
            $text = str_replace("{{$key}}", (string) $value, $text);
        }

        return $text;
    }

    /**
     * Get all prompts.
     */
    public function getAll(): Collection
    {
        return AiPrompt::all();
    }

    /**
     * Get all prompts filtered by pipeline.
     */
    public function getAllByPipeline(string $pipeline): Collection
    {
        return AiPrompt::query()->byPipeline($pipeline)->get();
    }

    /**
     * Update a prompt's text. Snapshots current version before updating.
     * Wraps in DB transaction for atomicity.
     */
    public function update(int $id, string $promptText, ?string $description = null): AiPrompt
    {
        return DB::transaction(function () use ($id, $promptText, $description) {
            $prompt = AiPrompt::findOrFail($id);

            // Snapshot current version before mutation
            AiPromptVersion::create([
                'ai_prompt_id' => $prompt->id,
                'prompt_text' => $prompt->prompt_text,
                'prompt_version' => $prompt->prompt_version,
            ]);

            // Update prompt
            $updateData = [
                'prompt_text' => $promptText,
                'prompt_version' => $prompt->prompt_version + 1,
            ];

            if ($description !== null) {
                $updateData['description'] = $description;
            }

            $prompt->update($updateData);

            $this->flushCache();

            return $prompt->fresh();
        });
    }

    /**
     * Get version history for a prompt, ordered by most recent first.
     */
    public function getHistory(int $id): Collection
    {
        $prompt = AiPrompt::findOrFail($id);

        return $prompt->versions()->orderBy('created_at', 'desc')->get();
    }

    /**
     * Revert a prompt to a specific version.
     * Validates the version belongs to the prompt, snapshots current, then restores.
     */
    public function revertToVersion(int $id, int $versionId): AiPrompt
    {
        return DB::transaction(function () use ($id, $versionId) {
            $prompt = AiPrompt::findOrFail($id);

            $version = AiPromptVersion::where('id', $versionId)
                ->where('ai_prompt_id', $prompt->id)
                ->firstOrFail();

            // Snapshot current version before mutation
            AiPromptVersion::create([
                'ai_prompt_id' => $prompt->id,
                'prompt_text' => $prompt->prompt_text,
                'prompt_version' => $prompt->prompt_version,
            ]);

            // Restore text from the target version
            $prompt->update([
                'prompt_text' => $version->prompt_text,
                'prompt_version' => $prompt->prompt_version + 1,
            ]);

            $this->flushCache();

            return $prompt->fresh();
        });
    }

    /**
     * Flush all ai_prompts cache entries.
     * Uses pattern-based forget since file cache doesn't support tags.
     */
    public function flushCache(): void
    {
        $prompts = AiPrompt::all(['slug', 'pipeline']);

        foreach ($prompts as $prompt) {
            Cache::forget("ai_prompts:{$prompt->slug}:{$prompt->pipeline}");
        }
    }
}
