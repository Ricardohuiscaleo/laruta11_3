<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPromptVersion extends Model
{
    public $timestamps = false;

    protected $table = 'ai_prompt_versions';

    protected $fillable = [
        'ai_prompt_id',
        'prompt_text',
        'prompt_version',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiPromptVersion $version) {
            $version->created_at = $version->freshTimestamp();
        });
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(AiPrompt::class, 'ai_prompt_id');
    }
}
