<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CapitalTrabajo extends Model
{
    protected $table = 'capital_trabajo';

    protected $fillable = [
        'fecha',
        'saldo_inicial',
        'ingresos_ventas',
        'egresos_compras',
        'egresos_gastos',
        'saldo_final',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'saldo_inicial' => 'float',
        'ingresos_ventas' => 'float',
        'egresos_compras' => 'float',
        'egresos_gastos' => 'float',
        'saldo_final' => 'float',
    ];
}
