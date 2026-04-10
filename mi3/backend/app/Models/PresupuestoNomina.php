<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PresupuestoNomina extends Model
{
    protected $table = 'presupuesto_nomina';
    public $timestamps = false;

    protected $fillable = [
        'mes', 'monto', 'centro_costo',
    ];

    protected $casts = [
        'monto' => 'float',
    ];
}
