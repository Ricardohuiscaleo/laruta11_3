<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PagoNomina extends Model
{
    protected $table = 'pagos_nomina';
    public $timestamps = false;

    protected $fillable = [
        'mes', 'personal_id', 'nombre', 'monto',
        'es_externo', 'notas', 'centro_costo',
    ];

    protected $casts = [
        'monto' => 'float',
        'mes' => 'date',
    ];
}
