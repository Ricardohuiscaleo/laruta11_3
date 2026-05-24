<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    */

    'vapid' => [
        'public_key' => env('VAPID_PUBLIC_KEY'),
        'private_key' => env('VAPID_PRIVATE_KEY'),
    ],

    'telegram' => [
        'token' => env('TELEGRAM_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
        'laruta11_token' => env('TELEGRAM_LARUTA11_TOKEN', ''),
        'laruta11_chat_id' => env('TELEGRAM_LARUTA11_CHAT_ID', ''),
    ],

    'rl6_resumen_token' => env('RL6_RESUMEN_TOKEN', ''),

];
