<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Checklist extends Model
{
    protected $table = 'checklists';

    protected $fillable = [
        'type', 'scheduled_time', 'scheduled_date',
        'started_at', 'completed_at', 'status',
        'user_id', 'user_name', 'personal_id', 'rol',
        'checklist_mode', 'total_items', 'completed_items',
        'completion_percentage', 'notes',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'completion_percentage' => 'float',
    ];

    public function items()
    {
        return $this->hasMany(ChecklistItem::class, 'checklist_id');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function virtual()
    {
        return $this->hasOne(ChecklistVirtual::class, 'checklist_id');
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('status', ['pending', 'active']);
    }

    public function scopeByRol($query, string $rol)
    {
        return $query->where('rol', $rol);
    }

    public function scopeByFecha($query, string $fecha)
    {
        return $query->where('scheduled_date', $fecha);
    }
}
