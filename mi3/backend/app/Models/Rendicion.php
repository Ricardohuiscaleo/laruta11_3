<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Rendicion extends Model
{
    protected $table = 'rendiciones';

    protected $fillable = [
        'token',
        'saldo_anterior',
        'total_compras',
        'saldo_resultante',
        'monto_transferido',
        'saldo_nuevo',
        'estado',
        'notas',
        'creado_por',
        'aprobado_por',
        'aprobado_at',
    ];

    protected $casts = [
        'saldo_anterior' => 'float',
        'total_compras' => 'float',
        'saldo_resultante' => 'float',
        'monto_transferido' => 'float',
        'saldo_nuevo' => 'float',
        'aprobado_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Rendicion $r) {
            if (empty($r->token)) {
                $r->token = Str::random(12);
            }
        });
    }

    public function compras()
    {
        return $this->hasMany(Compra::class, 'rendicion_id');
    }
}
