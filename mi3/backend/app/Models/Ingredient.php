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
    ];

    protected $casts = [
        'cost_per_unit' => 'float',
        'current_stock' => 'float',
        'min_stock_level' => 'float',
        'is_active' => 'boolean',
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
}
