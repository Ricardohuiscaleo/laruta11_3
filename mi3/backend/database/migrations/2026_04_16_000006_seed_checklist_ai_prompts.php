<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed checklist_ai_prompts with the 12 hardcoded prompts from PhotoAnalysisService::PROMPTS.
 * Uses a reflection approach to read the constant directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Read prompts from the existing hardcoded constant
        $reflection = new \ReflectionClass(\App\Services\Checklist\PhotoAnalysisService::class);
        $prompts = $reflection->getConstant('PROMPTS');

        if (!$prompts || !is_array($prompts)) {
            return;
        }

        $now = now()->format('Y-m-d H:i:s');

        foreach ($prompts as $contexto => $promptBase) {
            DB::table('checklist_ai_prompts')->insert([
                'contexto' => $contexto,
                'prompt_base' => $promptBase,
                'prompt_version' => 1,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('checklist_ai_prompts')->where('prompt_version', 1)->delete();
    }
};
