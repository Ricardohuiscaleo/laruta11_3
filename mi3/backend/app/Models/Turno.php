<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $table = 'turnos';
    public $timestamps = false;

    protected $fillable = [
        'personal_id', 'fecha', 'tipo', 'reemplazado_por',
        'monto_reemplazo', 'pago_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto_reemplazo' => 'float',
    ];

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function titular()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function reemplazante()
    {
        return $this->belongsTo(Personal::class, 'reemplazado_por');
    }
}
