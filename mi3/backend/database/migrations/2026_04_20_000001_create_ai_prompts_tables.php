<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('slug', 80);
            $table->string('pipeline', 40);
            $table->string('label', 120);
            $table->text('description')->nullable();
            $table->mediumText('prompt_text');
            $table->json('variables')->nullable();
            $table->integer('prompt_version')->default(1);
            $table->tinyInteger('is_active')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['slug', 'pipeline'], 'idx_slug_pipeline');
            $table->index(['pipeline', 'is_active'], 'idx_pipeline_active');
        });

        Schema::create('ai_prompt_versions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ai_prompt_id');
            $table->mediumText('prompt_text');
            $table->integer('prompt_version');
            $table->timestamp('created_at')->useCurrent();

            $table->index('ai_prompt_id', 'idx_prompt_id');
            $table->foreign('ai_prompt_id', 'fk_ai_prompt_versions_prompt')
                ->references('id')
                ->on('ai_prompts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_versions');
        Schema::dropIfExists('ai_prompts');
    }
};
