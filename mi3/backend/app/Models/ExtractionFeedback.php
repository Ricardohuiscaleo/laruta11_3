<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractionFeedback extends Model
{
    protected $table = 'extraction_feedback';
    public $timestamps = false;

    protected $fillable = [
        'extraction_log_id',
        'compra_id',
        'field_name',
        'original_value',
        'corrected_value',
    ];

    public function extractionLog()
    {
        return $this->belongsTo(AiExtractionLog::class, 'extraction_log_id');
    }
}
