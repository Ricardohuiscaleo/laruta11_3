<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_index', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_normalizado', 255);
            $table->string('nombre_original', 255)->comment('Nombre tal como aparece en compras');
            $table->string('rut', 15)->nullable();
            $table->unsignedInteger('frecuencia')->default(1);
            $table->json('items_habituales')->nullable()->comment('Array de {nombre, frecuencia, precio_promedio}');
            $table->json('ultimo_precio_por_item')->nullable()->comment('Map de item_name → ultimo_precio');
            $table->date('primera_compra')->nullable();
            $table->date('ultima_compra')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('nombre_normalizado', 'idx_nombre');
            $table->index('rut', 'idx_rut');
        });

        // Descending index requires raw SQL (Laravel Blueprint doesn't support DESC indexes natively)
        DB::statement('CREATE INDEX idx_frecuencia ON supplier_index (frecuencia DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_index');
    }
};
