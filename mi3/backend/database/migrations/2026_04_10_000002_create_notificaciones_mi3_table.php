<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_mi3', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('personal_id');
            $table->enum('tipo', ['turno', 'liquidacion', 'credito', 'ajuste', 'sistema']);
            $table->string('titulo', 255);
            $table->text('mensaje')->nullable();
            $table->tinyInteger('leida')->default(0);
            $table->unsignedInteger('referencia_id')->nullable();
            $table->string('referencia_tipo', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('personal_id');
            $table->index(['personal_id', 'leida']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_mi3');
    }
};
