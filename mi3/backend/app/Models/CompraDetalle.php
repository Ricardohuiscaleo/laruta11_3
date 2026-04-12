<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompraDetalle extends Model
{
    protected $table = 'compras_detalle';
    public $timestamps = false;

    protected $fillable = [
        'compra_id',
        'ingrediente_id',
        'product_id',
        'item_type',
        'nombre_item',
        'cantidad',
        'unidad',
        'precio_unitario',
        'subtotal',
        'stock_antes',
        'stock_despues',
    ];

    protected $casts = [
        'cantidad' => 'float',
        'precio_unitario' => 'float',
        'subtotal' => 'float',
        'stock_antes' => 'float',
        'stock_despues' => 'float',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function ingrediente()
    {
        return $this->belongsTo(Ingredient::class, 'ingrediente_id');
    }

    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
