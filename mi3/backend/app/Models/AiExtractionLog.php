<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiExtractionLog extends Model
{
    protected $table = 'ai_extraction_logs';
    public $timestamps = false;

    protected $fillable = [
        'compra_id',
        'image_url',
        'raw_response',
        'extracted_data',
        'confidence_scores',
        'overall_confidence',
        'processing_time_ms',
        'model_id',
        'status',
        'error_message',
    ];

    protected $casts = [
        'raw_response' => 'array',
        'extracted_data' => 'array',
        'confidence_scores' => 'array',
        'overall_confidence' => 'float',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function feedbacks()
    {
        return $this->hasMany(ExtractionFeedback::class, 'extraction_log_id');
    }
}
