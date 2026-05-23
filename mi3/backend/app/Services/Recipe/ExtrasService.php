<?php

declare(strict_types=1);

namespace App\Services\Recipe;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExtrasService
{
    const CATEGORY_NAME = 'Personalizar';

    public function getExtras(): Collection
    {
        $categoryIds = DB::table('categories')
            ->where('name', self::CATEGORY_NAME)
            ->pluck('id')
            ->toArray();

        if (empty($categoryIds)) {
            return collect();
        }

        $products = DB::table('products')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('subcategories', 'products.subcategory_id', '=', 'subcategories.id')
            ->whereIn('products.category_id', $categoryIds)
            ->select([
                'products.*',
                'categories.name as category_name',
                'subcategories.name as subcategory_name',
            ])
            ->orderBy('products.name')
            ->get();

        return $products->map(function ($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price' => (float) $p->price,
                'cost_price' => (float) $p->cost_price,
                'category_id' => $p->category_id,
                'category_name' => $p->category_name,
                'subcategory_id' => $p->subcategory_id,
                'subcategory_name' => $p->subcategory_name,
                'image_url' => $p->image_url,
                'is_active' => (bool) $p->is_active,
                'sale_price' => $p->sale_price ? (float) $p->sale_price : null,
            ];
        });
    }

    public function createExtra(array $data): array
    {
        $category = DB::table('categories')->where('name', self::CATEGORY_NAME)->first();
        if (!$category) {
            throw ValidationException::withMessages([
                'category' => ['Categoría Personalizar no encontrada'],
            ]);
        }

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
            'category_id' => $category->id,
            'subcategory_id' => $data['subcategory_id'] ?? null,
            'cost_price' => $data['cost_price'] ?? 0,
            'sale_price' => $data['sale_price'] ?? null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (array) DB::table('products')->find($id);
    }

    public function updateExtra(int $id, array $data): array
    {
        $product = DB::table('products')->where('id', $id)->first();
        if (!$product) {
            throw ValidationException::withMessages(['id' => ['Producto no encontrado']]);
        }

        $updateData = array_intersect_key($data, array_flip([
            'name', 'description', 'price', 'cost_price', 'sale_price',
            'subcategory_id', 'is_active',
        ]));

        if (isset($data['name'])) {
            $duplicate = DB::table('products')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
                ->where('id', '!=', $id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => ['Ya existe otro producto con ese nombre'],
                ]);
            }
        }

        $updateData['updated_at'] = now();
        DB::table('products')->where('id', $id)->update($updateData);

        return (array) DB::table('products')->find($id);
    }
}
