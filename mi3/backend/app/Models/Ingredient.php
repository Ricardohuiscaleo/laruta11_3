<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    protected $table = 'ingredients';

    protected $fillable = [
        'name',
        'category',
        'unit',
        'cost_per_unit',
        'current_stock',
        'min_stock_level',
        'supplier',
        'barcode',
        'internal_code',
        'expiry_date',
        'is_active',
        'is_composite',
    ];

    protected $casts = [
        'cost_per_unit' => 'float',
        'current_stock' => 'float',
        'min_stock_level' => 'float',
        'is_active' => 'boolean',
        'is_composite' => 'boolean',
        'expiry_date' => 'date',
    ];

    public function detallesCompra()
    {
        return $this->hasMany(CompraDetalle::class, 'ingrediente_id');
    }

    public function recetas()
    {
        return $this->hasMany(\App\Models\ProductRecipe::class, 'ingredient_id');
    }

    /** Sub-recipe children (this ingredient is composite, these are its components) */
    public function subRecipeItems()
    {
        return $this->hasMany(\App\Models\IngredientRecipe::class, 'ingredient_id');
    }

    /** Parent recipes where this ingredient is a child component */
    public function parentRecipes()
    {
        return $this->hasMany(\App\Models\IngredientRecipe::class, 'child_ingredient_id');
    }
}
