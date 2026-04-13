<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistItem extends Model
{
    protected $table = 'checklist_items';
    public $timestamps = false;

    protected $fillable = [
        'checklist_id', 'item_order', 'description',
        'item_type', 'requires_photo', 'photo_url', 'is_completed',
        'completed_at', 'notes',
        'ai_score', 'ai_observations', 'ai_analyzed_at',
        'cash_expected', 'cash_actual', 'cash_difference', 'cash_result',
    ];

    protected $casts = [
        'requires_photo' => 'boolean',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
        'ai_analyzed_at' => 'datetime',
        'cash_expected' => 'decimal:2',
        'cash_actual' => 'decimal:2',
        'cash_difference' => 'decimal:2',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'checklist_id');
    }
}
