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
        'desglose_ingresos',
        'egresos_compras',
        'egresos_gastos',
        'desglose_gastos',
        'saldo_final',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'saldo_inicial' => 'float',
        'ingresos_ventas' => 'float',
        'desglose_ingresos' => 'array',
        'egresos_compras' => 'float',
        'egresos_gastos' => 'float',
        'desglose_gastos' => 'array',
        'saldo_final' => 'float',
    ];
}
