<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteSueldo extends Model
{
    protected $table = 'ajustes_sueldo';
    public $timestamps = false;

    protected $fillable = [
        'personal_id', 'mes', 'monto', 'concepto',
        'categoria_id', 'notas',
    ];

    protected $casts = [
        'monto' => 'float',
        'mes' => 'date',
    ];

    public function personal()
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function categoria()
    {
        return $this->belongsTo(AjusteCategoria::class, 'categoria_id');
    }
}
