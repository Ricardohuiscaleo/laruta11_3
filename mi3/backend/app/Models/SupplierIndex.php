<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierIndex extends Model
{
    protected $table = 'supplier_index';

    protected $fillable = [
        'nombre_normalizado',
        'nombre_original',
        'rut',
        'frecuencia',
        'items_habituales',
        'ultimo_precio_por_item',
        'primera_compra',
        'ultima_compra',
    ];

    protected $casts = [
        'items_habituales' => 'array',
        'ultimo_precio_por_item' => 'array',
        'primera_compra' => 'date',
        'ultima_compra' => 'date',
    ];
}
