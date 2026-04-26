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
            // Carnes — porcionadas pero requieren cocción (tiempos industria well-done)
            'Hamburguesa R11 200gr' => ['plancha', 480, true],
            'Filete Pechuga de Pollo' => ['plancha', 420, true],
            'Carne Molida' => ['plancha', 480, true],
            'Tocino' => ['plancha', 180, true],
            'Chorizo' => ['plancha', 300, true],
            'Churrasco' => ['plancha', 420, true],
            'Longaniza' => ['plancha', 300, true],
            // Panes — requieren tostado
            'Pan de Churrasco Frica' => ['tostado', 90, true],
            'Pan de Hamburguesa' => ['tostado', 90, true],
            'Pan Frica' => ['tostado', 90, true],
            // Vegetales — tomate se pela al momento, resto ya listo
            'Tomate' => ['pelado', 30, false],
            'Lechuga' => ['crudo', 0, true],
            'Palta' => ['pelado', 20, false],
            'Cebolla' => ['caramelizado', 180, true],
            // Papas — ya picadas al inicio del turno, fritura doble
            'Papas' => ['fritura', 420, true],
            'Papa' => ['fritura', 420, true],
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
