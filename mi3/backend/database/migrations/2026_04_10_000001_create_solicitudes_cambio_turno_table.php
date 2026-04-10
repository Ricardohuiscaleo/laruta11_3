<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_cambio_turno', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('solicitante_id');
            $table->unsignedInteger('compañero_id');
            $table->date('fecha_turno');
            $table->string('motivo', 255)->nullable();
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->unsignedInteger('aprobado_por')->nullable();
            $table->timestamps();
            $table->index('solicitante_id');
            $table->index('compañero_id');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_cambio_turno');
    }
};
