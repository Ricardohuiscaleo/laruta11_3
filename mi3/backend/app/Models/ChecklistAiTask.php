<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistAiTask extends Model
{
    protected $table = 'checklist_ai_tasks';

    protected $fillable = [
        'contexto',
        'problema_detectado',
        'foto_url_origen',
        'checklist_item_id_origen',
        'foto_url_mejora',
        'status',
        'veces_detectado',
    ];

    public function scopeByContexto($query, string $contexto)
    {
        return $query->where('contexto', $contexto);
    }

    public function scopePendientes($query)
    {
        return $query->whereIn('status', ['pendiente', 'no_mejorado']);
    }
}
