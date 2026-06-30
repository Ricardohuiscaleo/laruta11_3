<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NominaSnapshot extends Model
{
    protected $table = 'nomina_snapshots';

    protected $fillable = [
        'token',
        'mes',
        'data',
        'aprobado_por',
        'aprobado_at',
    ];

    protected $casts = [
        'data' => 'array',
        'aprobado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (NominaSnapshot $s) {
            if (empty($s->token)) {
                $s->token = Str::random(12);
            }
        });
    }
}
