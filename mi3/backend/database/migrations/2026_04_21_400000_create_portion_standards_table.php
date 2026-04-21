<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portion_standards', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id');
            $table->integer('ingredient_id');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 20)->default('g');
            $table->timestamps();

            $table->unique(['category_id', 'ingredient_id']);
            $table->index('category_id');
            $table->index('ingredient_id');
        });

        // Categories: 3=Hamburguesas, 2=Sandwiches, 4=Completos, 12=Papas, 8=Combos
        $standards = [
            // ── Hamburguesas (3) ──
            ['category_id' => 3, 'ingredient_id' => 48,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 3, 'ingredient_id' => 159, 'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 3, 'ingredient_id' => 14,  'quantity' => 150,  'unit' => 'g'],
            ['category_id' => 3, 'ingredient_id' => 13,  'quantity' => 100,  'unit' => 'g'],
            ['category_id' => 3, 'ingredient_id' => 15,  'quantity' => 80,   'unit' => 'g'],
            ['category_id' => 3, 'ingredient_id' => 19,  'quantity' => 30,   'unit' => 'g'],
            ['category_id' => 3, 'ingredient_id' => 46,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 3, 'ingredient_id' => 49,  'quantity' => 0.05, 'unit' => 'kg'],
            ['category_id' => 3, 'ingredient_id' => 26,  'quantity' => 10,   'unit' => 'g'],
            ['category_id' => 3, 'ingredient_id' => 42,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 3, 'ingredient_id' => 165, 'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 3, 'ingredient_id' => 43,  'quantity' => 1,    'unit' => 'unidad'],
            // ── Sandwiches (2) ──
            ['category_id' => 2, 'ingredient_id' => 159, 'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 2, 'ingredient_id' => 14,  'quantity' => 150,  'unit' => 'g'],
            ['category_id' => 2, 'ingredient_id' => 13,  'quantity' => 100,  'unit' => 'g'],
            ['category_id' => 2, 'ingredient_id' => 19,  'quantity' => 50,   'unit' => 'g'],
            ['category_id' => 2, 'ingredient_id' => 166, 'quantity' => 60,   'unit' => 'g'],
            ['category_id' => 2, 'ingredient_id' => 42,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 2, 'ingredient_id' => 165, 'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 2, 'ingredient_id' => 43,  'quantity' => 1,    'unit' => 'unidad'],
            // ── Completos (4) ──
            ['category_id' => 4, 'ingredient_id' => 47,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 4, 'ingredient_id' => 45,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 4, 'ingredient_id' => 14,  'quantity' => 100,  'unit' => 'g'],
            ['category_id' => 4, 'ingredient_id' => 13,  'quantity' => 100,  'unit' => 'g'],
            ['category_id' => 4, 'ingredient_id' => 19,  'quantity' => 30,   'unit' => 'g'],
            ['category_id' => 4, 'ingredient_id' => 30,  'quantity' => 15,   'unit' => 'g'],
            ['category_id' => 4, 'ingredient_id' => 69,  'quantity' => 10,   'unit' => 'g'],
            ['category_id' => 4, 'ingredient_id' => 64,  'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 4, 'ingredient_id' => 43,  'quantity' => 1,    'unit' => 'unidad'],
            // ── Papas (12) ──
            ['category_id' => 12, 'ingredient_id' => 50, 'quantity' => 500,  'unit' => 'g'],
            ['category_id' => 12, 'ingredient_id' => 26, 'quantity' => 30,   'unit' => 'g'],
            ['category_id' => 12, 'ingredient_id' => 19, 'quantity' => 60,   'unit' => 'g'],
            ['category_id' => 12, 'ingredient_id' => 65, 'quantity' => 1,    'unit' => 'unidad'],
            ['category_id' => 12, 'ingredient_id' => 43, 'quantity' => 1,    'unit' => 'unidad'],
        ];

        DB::table('portion_standards')->insert(
            array_map(fn ($s) => array_merge($s, ['created_at' => now(), 'updated_at' => now()]), $standards)
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('portion_standards');
    }
};
