<?php

return [
    'name' => env('APP_NAME', 'mi3'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'https://api-mi3.laruta11.cl'),
    'timezone' => 'America/Santiago',
    'locale' => 'es',
    'fallback_locale' => 'es',
    'faker_locale' => 'es_CL',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
    'maintenance' => [
        'driver' => 'file',
    ],
];
