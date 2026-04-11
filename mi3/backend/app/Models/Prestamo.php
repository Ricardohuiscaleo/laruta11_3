<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prestamo extends Model
{
    protected $table = 'prestamos';

    protected $fillable = [
        'personal_id',
        'monto_solicitado',
        'monto_aprobado',
        'motivo',
        'cuotas',
        'cuotas_pagadas',
        'estado',
        'aprobado_por',
        'fecha_aprobacion',
        'fecha_inicio_descuento',
        'notas_admin',
    ];

    protected $casts = [
        'monto_solicitado' => 'float',
        'monto_aprobado' => 'float',
        'fecha_aprobacion' => 'datetime',
        'fecha_inicio_descuento' => 'date',
    ];

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(Personal::class, 'aprobado_por');
    }
}
