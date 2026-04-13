<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTemplate extends Model
{
    protected $table = 'checklist_templates';
    public $timestamps = false;

    protected $fillable = [
        'type', 'rol', 'item_order',
        'description', 'item_type', 'requires_photo', 'active',
    ];

    protected $casts = [
        'requires_photo' => 'boolean',
        'active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByRol($query, string $rol)
    {
        return $query->where('rol', $rol);
    }
}
