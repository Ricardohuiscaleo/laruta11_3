<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_equivalences', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_visual', 255)->comment('Lo que la IA ve: "caja de tomates", "bolsa de pan", "bandeja de carne"');
            $table->string('nombre_normalizado', 255)->index()->comment('Nombre normalizado en minúsculas para matching');
            $table->unsignedInteger('ingrediente_id')->nullable()->comment('FK a ingredients si aplica');
            $table->unsignedInteger('product_id')->nullable()->comment('FK a products si aplica');
            $table->string('item_type', 20)->default('ingredient');
            $table->string('nombre_ingrediente', 255)->comment('Nombre real del ingrediente: "Tomate"');
            $table->decimal('cantidad_por_unidad', 10, 2)->comment('Ej: 1 caja = 6 kg');
            $table->string('unidad_visual', 50)->default('caja')->comment('Unidad que se ve: caja, bolsa, bandeja, saco');
            $table->string('unidad_real', 50)->default('kg')->comment('Unidad real: kg, unidad, litro');
            $table->unsignedInteger('veces_confirmado')->default(0)->comment('Cuántas veces el usuario confirmó esta equivalencia');
            $table->decimal('ultimo_precio_unidad_visual', 10, 2)->nullable()->comment('Último precio por caja/bolsa/bandeja');
            $table->decimal('ultimo_precio_unitario', 10, 2)->nullable()->comment('Último precio por kg/unidad');
            $table->timestamps();

            $table->index('ingrediente_id');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_equivalences');
    }
};
