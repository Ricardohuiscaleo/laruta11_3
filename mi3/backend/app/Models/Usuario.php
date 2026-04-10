<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'email', 'telefono', 'session_token',
        'es_credito_r11', 'credito_r11_aprobado', 'limite_credito_r11',
        'credito_r11_usado', 'credito_r11_bloqueado',
        'fecha_aprobacion_r11', 'fecha_ultimo_pago_r11', 'relacion_r11',
    ];

    protected $casts = [
        'es_credito_r11' => 'boolean',
        'credito_r11_aprobado' => 'boolean',
        'credito_r11_bloqueado' => 'boolean',
        'limite_credito_r11' => 'float',
        'credito_r11_usado' => 'float',
    ];

    protected $hidden = [
        'password',
        'session_token',
    ];

    public function personal()
    {
        return $this->hasOne(Personal::class, 'user_id');
    }

    public function r11Transactions()
    {
        return $this->hasMany(R11CreditTransaction::class, 'user_id');
    }
}
