<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BeverageService
{
    /**
     * Get all beverage ingredients with linked product info.
     * GET /api/v1/admin/bebidas
     */
    public function getBeverages(): Collection
    {
        $beverages = DB::table('ingredients')
            ->where('category', 'Bebidas')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $ingredientIds = $beverages->pluck('id')->toArray();

        // Get linked products via product_recipes → products
        $linkedProducts = DB::table('product_recipes')
            ->join('products', 'products.id', '=', 'product_recipes.product_id')
            ->whereIn('product_recipes.ingredient_id', $ingredientIds)
            ->where('products.is_active', true)
            ->select(
                'product_recipes.ingredient_id',
                'products.id',
                'products.name',
                'products.price'
            )
            ->get()
            ->groupBy('ingredient_id');

        return $beverages->map(function ($b) use ($linkedProducts) {
            $products = $linkedProducts->get($b->id, collect());

            return [
                'id' => $b->id,
                'name' => $b->name,
                'category' => $b->category,
                'unit' => $b->unit,
                'cost_per_unit' => (float) $b->cost_per_unit,
                'current_stock' => (float) $b->current_stock,
                'min_stock_level' => (float) $b->min_stock_level,
                'supplier' => $b->supplier,
                'is_low_stock' => (float) $b->current_stock < (float) $b->min_stock_level,
                'linked_products' => $products->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                ])->values()->toArray(),
            ];
        });
    }

    /**
     * Create a new beverage ingredient.
     * Validates name uniqueness (case-insensitive).
     *
     * @param  array $data  Keys: name, unit, cost_per_unit, supplier?, min_stock_level?
     * @return array The created ingredient record
     *
     * @throws ValidationException
     */
    public function createBeverageIngredient(array $data): array
    {
        // Case-insensitive duplicate check
        $exists = DB::table('ingredients')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['El nombre ya existe en ingredientes'],
            ]);
        }

        $id = DB::table('ingredients')->insertGetId([
            'name' => $data['name'],
            'category' => 'Bebidas',
            'unit' => $data['unit'],
            'cost_per_unit' => $data['cost_per_unit'],
            'supplier' => $data['supplier'] ?? null,
            'min_stock_level' => $data['min_stock_level'] ?? 1,
            'current_stock' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $record = DB::table('ingredients')->find($id);

        return (array) $record;
    }

    /**
     * Create a beverage product + product_recipes link.
     * Finds or creates "Bebidas" product category.
     *
     * @param  array $data  Keys: name, price, description?, ingredient_id
     * @return array { product: [...], recipe: [...] }
     */
    public function createBeverageProduct(array $data): array
    {
        return DB::transaction(function () use ($data) {
            // Find or create "Bebidas" product category
            $category = DB::table('categories')
                ->where('name', 'Bebidas')
                ->first();

            if (!$category) {
                $categoryId = DB::table('categories')->insertGetId([
                    'name' => 'Bebidas',
                    'is_active' => true,
                    'sort_order' => 99,
                ]);
            } else {
                $categoryId = $category->id;
            }

            // Get the ingredient to know its unit
            $ingredient = DB::table('ingredients')->find($data['ingredient_id']);

            // Create product
            $productId = DB::table('products')->insertGetId([
                'name' => $data['name'],
                'price' => $data['price'],
                'description' => $data['description'] ?? null,
                'category_id' => $categoryId,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $unit = $ingredient ? $ingredient->unit : 'unidad';

            // Create product_recipes link (quantity=1)
            DB::table('product_recipes')->insert([
                'product_id' => $productId,
                'ingredient_id' => $data['ingredient_id'],
                'quantity' => 1,
                'unit' => $unit,
            ]);

            $product = DB::table('products')->find($productId);
            $recipe = DB::table('product_recipes')
                ->where('product_id', $productId)
                ->where('ingredient_id', $data['ingredient_id'])
                ->first();

            return [
                'product' => (array) $product,
                'recipe' => (array) $recipe,
            ];
        });
    }
}
