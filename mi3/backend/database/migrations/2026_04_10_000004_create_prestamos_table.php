<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestamos', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('personal_id');
            $table->decimal('monto_solicitado', 10, 2);
            $table->decimal('monto_aprobado', 10, 2)->nullable();
            $table->string('motivo', 255)->nullable();
            $table->integer('cuotas')->default(1);
            $table->integer('cuotas_pagadas')->default(0);
            $table->enum('estado', ['pendiente', 'aprobado', 'rechazado', 'pagado', 'cancelado'])->default('pendiente');
            $table->unsignedInteger('aprobado_por')->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->date('fecha_inicio_descuento')->nullable();
            $table->text('notas_admin')->nullable();
            $table->timestamps();

            $table->index('personal_id', 'idx_personal_id');
            $table->index('estado', 'idx_estado');
            $table->index('created_at', 'idx_created_at');

            $table->foreign('personal_id')->references('id')->on('personal');
            $table->foreign('aprobado_por')->references('id')->on('personal');
        });

        // Seed categoría 'prestamo' en ajustes_categorias
        DB::table('ajustes_categorias')->insertOrIgnore([
            'nombre' => 'Cuota Préstamo',
            'slug' => 'prestamo',
            'icono' => '💰',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('prestamos');

        DB::table('ajustes_categorias')
            ->where('slug', 'prestamo')
            ->delete();
    }
};
