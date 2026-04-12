<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_extraction_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('compra_id')->nullable();
            $table->string('image_url', 500);
            $table->json('raw_response')->comment('Respuesta cruda de Bedrock');
            $table->json('extracted_data')->comment('Datos parseados estructurados');
            $table->json('confidence_scores')->comment('Score por campo (0.0-1.0)');
            $table->decimal('overall_confidence', 3, 2)->default(0.00);
            $table->unsignedInteger('processing_time_ms')->default(0);
            $table->string('model_id', 100)->default('amazon.nova-lite-v1:0');
            $table->enum('status', ['success', 'failed', 'partial'])->default('success');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('compra_id', 'idx_compra');
            $table->index('status', 'idx_status');
            $table->index('created_at', 'idx_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_extraction_logs');
    }
};
