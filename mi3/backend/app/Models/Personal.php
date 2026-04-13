<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Personal extends Model
{
    protected $table = 'personal';
    public $timestamps = false;

    protected $fillable = [
        'nombre', 'rol', 'user_id', 'rut', 'telefono', 'email',
        'sueldo_base_cajero', 'sueldo_base_planchero',
        'sueldo_base_admin', 'sueldo_base_seguridad', 'activo',
        'foto_url',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'sueldo_base_cajero' => 'float',
        'sueldo_base_planchero' => 'float',
        'sueldo_base_admin' => 'float',
        'sueldo_base_seguridad' => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }

    public function turnos()
    {
        return $this->hasMany(Turno::class, 'personal_id');
    }

    public function ajustes()
    {
        return $this->hasMany(AjusteSueldo::class, 'personal_id');
    }

    public function notificaciones()
    {
        return $this->hasMany(NotificacionMi3::class, 'personal_id');
    }

    public function prestamos()
    {
        return $this->hasMany(Prestamo::class, 'personal_id');
    }

    public function getRolesArray(): array
    {
        return array_map('trim', explode(',', $this->rol ?? ''));
    }

    public function isAdmin(): bool
    {
        $roles = $this->getRolesArray();
        return in_array('administrador', $roles) || in_array('dueño', $roles);
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRolesArray());
    }
}
