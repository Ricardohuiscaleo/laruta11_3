<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistVirtual extends Model
{
    protected $table = 'checklist_virtual';
    public $timestamps = false;

    protected $fillable = [
        'checklist_id', 'personal_id',
        'confirmation_text', 'improvement_idea',
        'completed_at', 'created_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function checklist()
    {
        return $this->belongsTo(Checklist::class, 'checklist_id');
    }

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }
}
