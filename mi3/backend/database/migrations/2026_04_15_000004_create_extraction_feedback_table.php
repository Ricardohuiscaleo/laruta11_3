<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('extraction_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('extraction_log_id');
            $table->integer('compra_id');
            $table->string('field_name', 50)->comment('Campo corregido: proveedor, item_nombre, cantidad, precio, etc.');
            $table->text('original_value')->comment('Valor extraído por IA');
            $table->text('corrected_value')->comment('Valor corregido por usuario');
            $table->timestamp('created_at')->useCurrent();

            $table->index('extraction_log_id', 'idx_extraction');
            $table->index('compra_id', 'idx_compra');
            $table->index('field_name', 'idx_field');
            $table->foreign('extraction_log_id')->references('id')->on('ai_extraction_logs')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_feedback');
    }
};
