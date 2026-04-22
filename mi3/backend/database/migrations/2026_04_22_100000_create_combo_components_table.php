<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactorización de Combos — Tabla unificada combo_components
 *
 * Reemplaza las tablas legacy: combos + combo_items + combo_selections
 * Conecta productos combo (category_id=8) con sus productos hijos directamente.
 *
 * 1. Crea tabla combo_components
 * 2. Migra combo_items (is_selectable=0) → combo_components (is_fixed=1)
 * 3. Migra combo_selections → combo_components (is_fixed=0)
 * 4. Limpia product_recipes redundantes de combos
 * 5. Corrige typo en nombre de producto id=233
 */
return new class extends Migration
{
    /**
     * Mapping combos.id → products.id (verificado en producción)
     */
    private const COMBO_MAPPING = [
        1   => 187,  // Combo Doble Mixta
        2   => 188,  // Combo Completo
        3   => 190,  // Combo Gorda
        4   => 198,  // Combo Dupla
        211 => 211,  // Combo Completo Familiar
        233 => 233,  // Combo Hamburguesa Clásica
        234 => 242,  // Combo Salchipapa
    ];

    public function up(): void
    {
        // 1. Crear tabla combo_components
        if (! Schema::hasTable('combo_components')) {
            Schema::create('combo_components', function (Blueprint $table) {
                $table->increments('id');
                $table->integer('combo_product_id')->comment('FK → products.id (category_id=8)');
                $table->integer('child_product_id')->comment('FK → products.id (producto hijo)');
                $table->integer('quantity')->default(1);
                $table->tinyInteger('is_fixed')->default(1)->comment('1=fijo, 0=seleccionable');
                $table->string('selection_group', 50)->nullable()->comment('Nombre del grupo de selección');
                $table->integer('max_selections')->default(1);
                $table->decimal('price_adjustment', 10, 2)->default(0);
                $table->integer('sort_order')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->index('combo_product_id', 'idx_combo');
                $table->index('child_product_id', 'idx_child');
            });
        }

        // 2-5. Seed data solo si las tablas legacy existen (producción MySQL)
        if (Schema::hasTable('combo_items')) {
            $this->seedData();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('combo_components');
    }

    private function seedData(): void
    {
        $now = now()->format('Y-m-d H:i:s');

        // 2. Migrar combo_items (is_selectable=0) → combo_components (is_fixed=1)
        $this->migrateFixedItems($now);

        // 3. Migrar combo_selections → combo_components (is_fixed=0)
        $this->migrateSelections($now);

        // 4. Limpiar product_recipes redundantes de combos (category_id=8)
        DB::statement('
            DELETE FROM product_recipes
            WHERE product_id IN (
                SELECT id FROM products WHERE category_id = 8
            )
        ');

        // 5. Corregir typo: "Combo Haburguesa Cásica" → "Combo Hamburguesa Clásica"
        DB::table('products')
            ->where('id', 233)
            ->update(['name' => 'Combo Hamburguesa Clásica']);
    }

    private function migrateFixedItems(string $now): void
    {
        $fixedItems = DB::table('combo_items')
            ->where('is_selectable', 0)
            ->get();

        $sortOrder = 0;
        foreach ($fixedItems as $item) {
            $comboProductId = self::COMBO_MAPPING[$item->combo_id] ?? null;

            if ($comboProductId === null) {
                continue; // combo_id no mapeado, saltar
            }

            DB::table('combo_components')->insert([
                'combo_product_id'  => $comboProductId,
                'child_product_id'  => $item->product_id,
                'quantity'          => $item->quantity ?? 1,
                'is_fixed'          => 1,
                'selection_group'   => null,
                'max_selections'    => 1,
                'price_adjustment'  => 0,
                'sort_order'        => $sortOrder++,
                'created_at'        => $now,
            ]);
        }
    }

    private function migrateSelections(string $now): void
    {
        $selections = DB::table('combo_selections')->get();

        $sortOrder = 0;
        foreach ($selections as $sel) {
            $comboProductId = self::COMBO_MAPPING[$sel->combo_id] ?? null;

            if ($comboProductId === null) {
                continue; // combo_id no mapeado, saltar
            }

            DB::table('combo_components')->insert([
                'combo_product_id'  => $comboProductId,
                'child_product_id'  => $sel->product_id,
                'quantity'          => 1,
                'is_fixed'          => 0,
                'selection_group'   => $sel->selection_group,
                'max_selections'    => $sel->max_selections ?? 1,
                'price_adjustment'  => $sel->additional_price ?? 0,
                'sort_order'        => $sortOrder++,
                'created_at'        => $now,
            ]);
        }
    }
};
