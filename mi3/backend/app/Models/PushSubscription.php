<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $table = 'push_subscriptions_mi3';

    protected $fillable = [
        'personal_id',
        'subscription',
        'is_active',
    ];

    protected $casts = [
        'subscription' => 'array',
        'is_active' => 'boolean',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
