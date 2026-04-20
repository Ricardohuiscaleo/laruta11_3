<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngredientRecipe extends Model
{
    protected $table = 'ingredient_recipes';

    protected $fillable = [
        'ingredient_id',
        'child_ingredient_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function parent()
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function child()
    {
        return $this->belongsTo(Ingredient::class, 'child_ingredient_id');
    }
}
