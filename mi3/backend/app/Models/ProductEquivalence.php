<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductEquivalence extends Model
{
    protected $table = 'product_equivalences';

    protected $fillable = [
        'nombre_visual',
        'nombre_normalizado',
        'ingrediente_id',
        'product_id',
        'item_type',
        'nombre_ingrediente',
        'cantidad_por_unidad',
        'unidad_visual',
        'unidad_real',
        'veces_confirmado',
        'ultimo_precio_unidad_visual',
        'ultimo_precio_unitario',
    ];

    protected $casts = [
        'cantidad_por_unidad' => 'float',
        'ultimo_precio_unidad_visual' => 'float',
        'ultimo_precio_unitario' => 'float',
    ];

    public function ingrediente()
    {
        return $this->belongsTo(Ingredient::class, 'ingrediente_id');
    }

    public function producto()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
