<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'subcategory_id',
        'name',
        'description',
        'price',
        'cost_price',
        'image_url',
        'sku',
        'barcode',
        'stock_quantity',
        'min_stock_level',
        'is_active',
        'has_variants',
        'preparation_time',
        'calories',
        'allergens',
        'grams',
        'is_featured',
        'sale_price',
    ];

    protected $casts = [
        'price' => 'float',
        'cost_price' => 'float',
        'sale_price' => 'float',
        'is_active' => 'boolean',
        'has_variants' => 'boolean',
        'is_featured' => 'boolean',
    ];

    public function detallesCompra()
    {
        return $this->hasMany(CompraDetalle::class, 'product_id');
    }
}
