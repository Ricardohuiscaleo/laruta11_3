<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComboComponent extends Model
{
    protected $table = 'combo_components';

    const UPDATED_AT = null;

    protected $fillable = [
        'combo_product_id',
        'child_product_id',
        'quantity',
        'is_fixed',
        'selection_group',
        'max_selections',
        'price_adjustment',
        'sort_order',
    ];

    protected $casts = [
        'is_fixed' => 'boolean',
        'price_adjustment' => 'float',
    ];

    public function combo()
    {
        return $this->belongsTo(Product::class, 'combo_product_id');
    }

    public function childProduct()
    {
        return $this->belongsTo(Product::class, 'child_product_id');
    }
}
