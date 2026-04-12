<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiTrainingDataset extends Model
{
    protected $table = 'ai_training_dataset';
    public $timestamps = false;

    protected $fillable = [
        'compra_id',
        'image_url',
        'extraction_log_id',
        'real_data',
        'extracted_data',
        'precision_scores',
        'overall_precision',
        'processed_at',
        'batch_id',
    ];

    protected $casts = [
        'real_data' => 'array',
        'extracted_data' => 'array',
        'precision_scores' => 'array',
        'overall_precision' => 'float',
        'processed_at' => 'datetime',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }
}
