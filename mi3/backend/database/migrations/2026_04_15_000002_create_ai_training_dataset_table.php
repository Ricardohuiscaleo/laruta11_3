<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_training_dataset', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('compra_id');
            $table->string('image_url', 500);
            $table->unsignedBigInteger('extraction_log_id')->nullable();
            $table->json('real_data')->comment('Datos reales de la compra (ground truth)');
            $table->json('extracted_data')->nullable()->comment('Datos extraídos por IA');
            $table->json('precision_scores')->nullable()->comment('Precisión por campo vs real');
            $table->decimal('overall_precision', 5, 2)->nullable()->comment('Precisión global 0-100%');
            $table->timestamp('processed_at')->nullable();
            $table->string('batch_id', 50)->nullable()->comment('ID del batch de procesamiento');
            $table->timestamp('created_at')->useCurrent();

            $table->index('compra_id', 'idx_compra');
            $table->index('batch_id', 'idx_batch');
            $table->index('processed_at', 'idx_processed');
            $table->foreign('compra_id')->references('id')->on('compras')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_training_dataset');
    }
};
