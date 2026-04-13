<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistAiTraining extends Model
{
    protected $table = 'checklist_ai_training';

    const UPDATED_AT = null;

    protected $fillable = [
        'checklist_item_id',
        'photo_url',
        'contexto',
        'ai_score',
        'ai_observations',
        'admin_feedback',
        'admin_notes',
        'admin_score',
        'prompt_used',
    ];

    public function item()
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }
}
