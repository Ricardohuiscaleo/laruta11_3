<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class R11CreditTransaction extends Model
{
    protected $table = 'r11_credit_transactions';
    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'amount', 'type', 'description', 'order_id',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'user_id');
    }
}
