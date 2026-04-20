<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-Recetas de Ingredientes Compuestos
 *
 * 1. Crea tabla ingredient_recipes (relación padre-hijo entre ingredientes)
 * 2. Agrega columna is_composite a ingredients
 * 3. Seed: inserta "Carne Molida", marca Hamburguesa R11 como compuesto,
 *    corrige stock de Tocino (unidades→kg), y crea sub-receta de Hamburguesa R11
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Crear tabla ingredient_recipes
        if (! Schema::hasTable('ingredient_recipes')) {
            Schema::create('ingredient_recipes', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('ingredient_id')->comment('parent composite ingredient');
                $table->integer('child_ingredient_id')->comment('child real ingredient');
                $table->decimal('quantity', 10, 3)->comment('quantity per 1 unit of parent');
                $table->string('unit', 20)->default('kg');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['ingredient_id', 'child_ingredient_id'], 'unique_parent_child');
                $table->index('ingredient_id', 'idx_ingredient');

                $table->foreign('ingredient_id', 'fk_ir_ingredient')
                    ->references('id')
                    ->on('ingredients')
                    ->onDelete('cascade');

                $table->foreign('child_ingredient_id', 'fk_ir_child_ingredient')
                    ->references('id')
                    ->on('ingredients')
                    ->onDelete('cascade');
            });
        }

        // 2. Agregar columna is_composite a ingredients
        if (! Schema::hasColumn('ingredients', 'is_composite')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->tinyInteger('is_composite')->default(0)->after('is_active');
            });
        }

        // 3. Seed data
        $this->seedData();
    }

    public function down(): void
    {
        // Eliminar sub-receta de Hamburguesa R11 (id=48)
        DB::table('ingredient_recipes')->where('ingredient_id', 48)->delete();

        // Revertir stock de Tocino (kg → unidades de 50g)
        DB::table('ingredients')
            ->where('id', 49)
            ->update(['current_stock' => DB::raw('current_stock / 0.05')]);

        // Quitar flag compuesto de Hamburguesa R11
        if (Schema::hasColumn('ingredients', 'is_composite')) {
            DB::table('ingredients')->where('id', 48)->update(['is_composite' => 0]);
        }

        // Eliminar "Carne Molida"
        DB::table('ingredients')->where('name', 'Carne Molida')->delete();

        // Eliminar columna is_composite
        if (Schema::hasColumn('ingredients', 'is_composite')) {
            Schema::table('ingredients', function (Blueprint $table) {
                $table->dropColumn('is_composite');
            });
        }

        // Eliminar tabla
        Schema::dropIfExists('ingredient_recipes');
    }

    private function seedData(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        // 3a. Insertar "Carne Molida" si no existe
        $carneMolidaId = DB::table('ingredients')->where('name', 'Carne Molida')->value('id');

        if (! $carneMolidaId) {
            $carneMolidaId = DB::table('ingredients')->insertGetId([
                'name'           => 'Carne Molida',
                'unit'           => 'kg',
                'category'       => 'Carnes',
                'cost_per_unit'  => 6490,
                'current_stock'  => 0,
                'min_stock_level'=> 0,
                'is_active'      => 1,
                'is_composite'   => 0,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }

        // 3b. Marcar Hamburguesa R11 200gr (id=48) como compuesto
        DB::table('ingredients')->where('id', 48)->update(['is_composite' => 1]);

        // 3c. Convertir stock de Tocino Laminado (id=49): unidades de 50g → kg
        DB::table('ingredients')
            ->where('id', 49)
            ->update(['current_stock' => DB::raw('current_stock * 0.05')]);

        // 3d. Insertar sub-receta de Hamburguesa R11 (id=48)
        $children = [
            ['child_ingredient_id' => $carneMolidaId, 'quantity' => 0.150, 'unit' => 'kg'],
            ['child_ingredient_id' => 49,             'quantity' => 0.040, 'unit' => 'kg'],  // Tocino
            ['child_ingredient_id' => 151,            'quantity' => 0.010, 'unit' => 'kg'],  // Longaniza
        ];

        foreach ($children as $child) {
            DB::table('ingredient_recipes')->insertOrIgnore([
                'ingredient_id'       => 48,
                'child_ingredient_id' => $child['child_ingredient_id'],
                'quantity'            => $child['quantity'],
                'unit'                => $child['unit'],
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }
};
