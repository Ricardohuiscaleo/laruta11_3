<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'fecha_compra',
        'proveedor',
        'tipo_compra',
        'monto_total',
        'metodo_pago',
        'estado',
        'notas',
        'imagen_respaldo',
        'usuario',
        'rendicion_id',
    ];

    protected $casts = [
        'imagen_respaldo' => 'array',
        'fecha_compra' => 'datetime',
        'monto_total' => 'float',
    ];

    public function detalles()
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function rendicion()
    {
        return $this->belongsTo(Rendicion::class, 'rendicion_id');
    }

    public function extractionLogs()
    {
        return $this->hasMany(AiExtractionLog::class, 'compra_id');
    }
}
