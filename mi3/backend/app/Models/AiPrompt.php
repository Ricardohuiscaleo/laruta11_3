<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiPrompt extends Model
{
    protected $table = 'ai_prompts';

    protected $fillable = [
        'slug',
        'pipeline',
        'label',
        'description',
        'prompt_text',
        'variables',
        'prompt_version',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeByPipeline($query, string $pipeline)
    {
        return $query->where('pipeline', $pipeline);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AiPromptVersion::class);
    }
}
