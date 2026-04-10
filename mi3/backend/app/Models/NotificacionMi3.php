<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificacionMi3 extends Model
{
    protected $table = 'notificaciones_mi3';
    const UPDATED_AT = null;

    protected $fillable = [
        'personal_id', 'tipo', 'titulo', 'mensaje',
        'leida', 'referencia_id', 'referencia_tipo',
    ];

    protected $casts = [
        'leida' => 'boolean',
    ];

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
