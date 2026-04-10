<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteCategoria extends Model
{
    protected $table = 'ajustes_categorias';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'slug', 'icono',
    ];
}
