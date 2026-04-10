<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudCambioTurno extends Model
{
    protected $table = 'solicitudes_cambio_turno';

    protected $fillable = [
        'solicitante_id', 'compañero_id', 'fecha_turno',
        'motivo', 'estado', 'aprobado_por',
    ];

    protected $casts = [
        'fecha_turno' => 'date',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Personal::class, 'solicitante_id');
    }

    public function compañero()
    {
        return $this->belongsTo(Personal::class, 'compañero_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(Personal::class, 'aprobado_por');
    }
}
