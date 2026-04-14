<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySettlement extends Model
{
    protected $table = 'daily_settlements';

    const CREATED_AT = 'created_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'settlement_date',
        'total_orders_delivered',
        'total_delivery_fees',
        'settlement_data',
        'status',
        'payment_voucher_url',
        'paid_at',
        'paid_by',
        'compra_id',
    ];

    protected $casts = [
        'settlement_date'     => 'date',
        'settlement_data'     => 'array',
        'paid_at'             => 'datetime',
        'total_delivery_fees' => 'float',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function pagadoPor()
    {
        return $this->belongsTo(Personal::class, 'paid_by');
    }
}
