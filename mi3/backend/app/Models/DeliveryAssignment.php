<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryAssignment extends Model
{
    protected $table = 'delivery_assignments';

    public $timestamps = false;

    protected $fillable = [
        'order_id',
        'rider_id',
        'assigned_by',
        'assigned_at',
        'picked_up_at',
        'delivered_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'assigned_at'  => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(TuuOrder::class, 'order_id');
    }

    public function rider()
    {
        return $this->belongsTo(Personal::class, 'rider_id');
    }
}
