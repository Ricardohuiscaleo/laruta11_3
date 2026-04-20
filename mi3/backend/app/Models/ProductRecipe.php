<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductRecipe extends Model
{
    protected $table = 'product_recipes';

    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'ingredient_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
