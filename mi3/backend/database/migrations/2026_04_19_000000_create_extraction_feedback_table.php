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
            $table->unsignedInteger('compra_id')->nullable();
            $table->string('proveedor', 255)->nullable();
            $table->string('tipo_imagen', 50)->nullable();
            $table->string('field_name', 100);
            $table->text('original_value')->nullable();
            $table->text('corrected_value')->nullable();
            $table->timestamp('created_at')->useCurrent();
            
            $table->index('proveedor', 'idx_proveedor');
            $table->index('tipo_imagen', 'idx_tipo_imagen');
            $table->index('extraction_log_id', 'idx_extraction_log');
            $table->index('created_at', 'idx_created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('extraction_feedback');
    }
};
