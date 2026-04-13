<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistAiPrompt extends Model
{
    protected $table = 'checklist_ai_prompts';

    protected $fillable = [
        'contexto',
        'prompt_base',
        'prompt_version',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByContexto($query, string $contexto)
    {
        return $query->where('contexto', $contexto);
    }
}
