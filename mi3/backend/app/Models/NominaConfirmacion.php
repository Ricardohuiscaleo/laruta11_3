<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaConfirmacion extends Model
{
    protected $table = 'nomina_confirmaciones';

    protected $fillable = [
        'nomina_snapshot_id',
        'personal_id',
        'confirmado_at',
    ];

    protected $casts = [
        'confirmado_at' => 'datetime',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(NominaSnapshot::class, 'nomina_snapshot_id');
    }
}
