<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortionStandard extends Model
{
    protected $fillable = [
        'category_id',
        'ingredient_id',
        'quantity',
        'unit',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function ingredient()
    {
        return $this->belongsTo(Ingredient::class);
    }
}
