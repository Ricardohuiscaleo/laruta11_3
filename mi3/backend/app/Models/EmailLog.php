<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'email_logs';
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'email_to', 'email_type', 'subject',
        'amount', 'gmail_message_id', 'gmail_thread_id',
        'status', 'sent_at',
    ];
}
