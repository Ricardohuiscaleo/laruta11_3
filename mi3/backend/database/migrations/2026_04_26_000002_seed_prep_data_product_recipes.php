<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mapeo por nombre de ingrediente → prep_method, prep_time_seconds, is_prepped
        // is_prepped = true significa que ya viene listo al inicio del turno
        $prepData = [
            // Carnes — porcionadas pero requieren cocción
            'Hamburguesa R11 200gr' => ['plancha', 300, true],
            'Filete Pechuga de Pollo' => ['plancha', 240, true],
            'Carne Molida' => ['plancha', 300, true],
            'Tocino' => ['plancha', 120, true],
            'Chorizo' => ['plancha', 180, true],
            'Churrasco' => ['plancha', 240, true],
            'Longaniza' => ['plancha', 180, true],
            // Panes — requieren tostado
            'Pan de Churrasco Frica' => ['tostado', 60, true],
            'Pan de Hamburguesa' => ['tostado', 60, true],
            'Pan Frica' => ['tostado', 60, true],
            // Vegetales — tomate se pela al momento, resto ya listo
            'Tomate' => ['pelado', 30, false],
            'Lechuga' => ['crudo', 0, true],
            'Palta' => ['pelado', 20, false],
            'Cebolla' => ['caramelizado', 180, true],
            // Papas — ya picadas al inicio del turno
            'Papas' => ['fritura', 300, true],
            'Papa' => ['fritura', 300, true],
            // Salsas y condimentos — listos
            'Mayonesa Kraft' => ['listo', 0, true],
            'Ketchup' => ['listo', 0, true],
            'Mostaza' => ['listo', 0, true],
            'Salsa BBQ' => ['listo', 0, true],
            'Sal' => ['listo', 0, true],
            'Pimienta' => ['listo', 0, true],
            'Orégano' => ['listo', 0, true],
            // Quesos — listos
            'Queso Cheddar' => ['listo', 0, true],
            'Queso Chanco' => ['listo', 0, true],
            'Queso Gouda' => ['listo', 0, true],
            // Embutidos — listos
            'Jamón' => ['listo', 0, true],
            // Aceites
            'Aceite Vegetal' => ['listo', 0, true],
            'Aceite de Oliva' => ['listo', 0, true],
        ];

        foreach ($prepData as $ingredientName => [$method, $time, $prepped]) {
            DB::table('product_recipes')
                ->join('ingredients', 'product_recipes.ingredient_id', '=', 'ingredients.id')
                ->where('ingredients.name', $ingredientName)
                ->update([
                    'product_recipes.prep_method' => $method,
                    'product_recipes.prep_time_seconds' => $time,
                    'product_recipes.is_prepped' => $prepped,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('product_recipes')->update([
            'prep_method' => null,
            'prep_time_seconds' => 0,
            'is_prepped' => false,
        ]);
    }
};
