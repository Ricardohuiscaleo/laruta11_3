<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiderLocation extends Model
{
    protected $table = 'rider_locations';

    public $timestamps = false;

    protected $fillable = [
        'rider_id',
        'latitud',
        'longitud',
        'precision_metros',
        'velocidad_kmh',
        'heading',
    ];

    protected $casts = [
        'latitud'      => 'float',
        'longitud'     => 'float',
        'velocidad_kmh' => 'float',
        'heading'      => 'float',
    ];

    public function rider()
    {
        return $this->belongsTo(Personal::class, 'rider_id');
    }
}
