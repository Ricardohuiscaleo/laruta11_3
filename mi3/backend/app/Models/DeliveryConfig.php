<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryConfig extends Model
{
    protected $table = 'delivery_config';

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'updated_by',
    ];

    /**
     * Get all config entries as an associative array ['config_key' => 'config_value', ...]
     */
    public static function getAllAsMap(): array
    {
        return self::pluck('config_value', 'config_key')->toArray();
    }
}
