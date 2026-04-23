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
        'nombre', 'email', 'telefono', 'session_token', 'foto_perfil',
        'es_credito_r11', 'credito_r11_aprobado', 'limite_credito_r11',
        'credito_r11_usado', 'credito_r11_bloqueado',
        'fecha_aprobacion_r11', 'fecha_ultimo_pago_r11', 'relacion_r11',
        // RL6 fields
        'es_militar_rl6', 'credito_aprobado', 'limite_credito',
        'credito_usado', 'credito_bloqueado', 'grado_militar',
        'unidad_trabajo', 'rut', 'fecha_ultimo_pago',
    ];

    protected $casts = [
        'es_credito_r11' => 'boolean',
        'credito_r11_aprobado' => 'boolean',
        'credito_r11_bloqueado' => 'boolean',
        'limite_credito_r11' => 'float',
        'credito_r11_usado' => 'float',
        // RL6 casts
        'es_militar_rl6' => 'boolean',
        'credito_aprobado' => 'boolean',
        'credito_bloqueado' => 'boolean',
        'limite_credito' => 'float',
        'credito_usado' => 'float',
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

    public function rl6Transactions()
    {
        return $this->hasMany(Rl6CreditTransaction::class, 'user_id');
    }

    public function emailLogs()
    {
        return $this->hasMany(EmailLog::class, 'user_id');
    }
}
