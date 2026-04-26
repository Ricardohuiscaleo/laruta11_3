<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BeverageService
{
    /**
     * Get all beverage products (from Snacks and Bebidas categories).
     * Returns products grouped by subcategory with stock and cost info.
     */
    public function getBeverages(): Collection
    {
        // Get Snacks and Bebidas category IDs
        $categoryIds = DB::table('categories')
            ->whereIn('name', ['Snacks', 'Bebidas'])
            ->pluck('id')
            ->toArray();

        if (empty($categoryIds)) {
            return collect();
        }

        $products = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
            ->whereIn('products.category_id', $categoryIds)
            ->where('products.is_active', true)
            ->select([
                'products.*',
                'categories.name as category_name',
                'subcategories.name as subcategory_name',
            ])
            ->orderBy('subcategories.sort_order')
            ->orderBy('subcategories.name')
            ->orderBy('products.name')
            ->get();

        // Get ingredient costs via product_recipes
        $productIds = $products->pluck('id')->toArray();
        $recipeCosts = DB::table('product_recipes')
            ->join('ingredients', 'ingredients.id', '=', 'product_recipes.ingredient_id')
            ->whereIn('product_recipes.product_id', $productIds)
            ->select('product_recipes.product_id', DB::raw('SUM(ingredients.cost_per_unit * product_recipes.quantity) as recipe_cost'))
            ->groupBy('product_recipes.product_id')
            ->pluck('recipe_cost', 'product_id');

        return $products->map(function ($p) use ($recipeCosts) {
            $cost = (float) ($recipeCosts[$p->id] ?? 0);
            $price = (float) $p->price;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => $price,
                'cost_price' => (float) $p->cost_price,
                'recipe_cost' => $cost,
                'stock_quantity' => (int) $p->stock_quantity,
                'min_stock_level' => (int) $p->min_stock_level,
                'is_low_stock' => (int) $p->stock_quantity < (int) $p->min_stock_level,
                'category_id' => $p->category_id,
                'category_name' => $p->category_name,
                'subcategory_id' => $p->subcategory_id,
                'subcategory_name' => $p->subcategory_name,
                'sku' => $p->sku,
                'image_url' => $p->image_url,
                'is_active' => (bool) $p->is_active,
            ];
        });
    }

    /**
     * Create a new beverage product in the products table.
     * Defaults to Snacks category (where beverages live).
     */
    public function createBeverageProduct(array $data): array
    {
        // Find Snacks category (where beverages live)
        $category = DB::table('categories')->where('name', 'Snacks')->first();
        if (! $category) {
            $category = DB::table('categories')->where('name', 'Bebidas')->first();
        }
        $categoryId = $category ? $category->id : null;

        // Case-insensitive duplicate check on products
        $exists = DB::table('products')
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Ya existe un producto con ese nombre'],
            ]);
        }

        $id = DB::table('products')->insertGetId([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'cost_price' => $data['cost_price'] ?? 0,
            'stock_quantity' => $data['stock_quantity'] ?? 0,
            'min_stock_level' => $data['min_stock_level'] ?? 5,
            'category_id' => $categoryId,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'sku' => $data['sku'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('products')->find($id);
    }

    /**
     * Get subcategories for Snacks/Bebidas categories.
     */
    public function getSubcategories(): Collection
    {
        $categoryIds = DB::table('categories')
            ->whereIn('name', ['Snacks', 'Bebidas'])
            ->pluck('id')
            ->toArray();

        return DB::table('subcategories')
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
