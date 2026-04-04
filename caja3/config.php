<?php
function _env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

$config = [
    'PUBLIC_SUPABASE_URL' => _env('PUBLIC_SUPABASE_URL'),
    'PUBLIC_SUPABASE_ANON_KEY' => _env('PUBLIC_SUPABASE_ANON_KEY'),
    'gemini_api_key' => _env('GEMINI_API_KEY'),
    'unsplash_access_key' => _env('UNSPLASH_ACCESS_KEY'),
    'google_calendar_api_key' => _env('GOOGLE_CALENDAR_API_KEY'),
    'google_client_id' => _env('GOOGLE_CLIENT_ID'),
    'google_client_secret' => _env('GOOGLE_CLIENT_SECRET'),

    'booking_db_host' => _env('BOOKING_DB_HOST'),
    'booking_db_name' => _env('BOOKING_DB_NAME'),
    'booking_db_user' => _env('BOOKING_DB_USER'),
    'booking_db_pass' => _env('BOOKING_DB_PASS'),

    'rag_db_host' => _env('RAG_DB_HOST'),
    'rag_db_name' => _env('RAG_DB_NAME'),
    'rag_db_user' => _env('RAG_DB_USER'),
    'rag_db_pass' => _env('RAG_DB_PASS'),

    'ruta11game_db_host' => _env('RUTA11GAME_DB_HOST'),
    'ruta11game_db_name' => _env('RUTA11GAME_DB_NAME'),
    'ruta11game_db_user' => _env('RUTA11GAME_DB_USER'),
    'ruta11game_db_pass' => _env('RUTA11GAME_DB_PASS'),

    'Calcularuta11_db_host' => _env('CALCULARUTA11_DB_HOST'),
    'Calcularuta11_db_name' => _env('CALCULARUTA11_DB_NAME'),
    'Calcularuta11_db_user' => _env('CALCULARUTA11_DB_USER'),
    'Calcularuta11_db_pass' => _env('CALCULARUTA11_DB_PASS'),

    'app_db_host' => _env('APP_DB_HOST'),
    'app_db_name' => _env('APP_DB_NAME'),
    'app_db_user' => _env('APP_DB_USER'),
    'app_db_pass' => _env('APP_DB_PASS'),

    'ruta11_db_host' => _env('RUTA11_DB_HOST'),
    'ruta11_db_name' => _env('RUTA11_DB_NAME'),
    'ruta11_db_user' => _env('RUTA11_DB_USER'),
    'ruta11_db_pass' => _env('RUTA11_DB_PASS'),

    'admin_users' => [
        _env('ADMIN_USER') ?: 'admin' => _env('ADMIN_PASS') ?: 'admin123',
        'ricardo' => _env('RICARDO_PASS'),
        'manager' => _env('MANAGER_PASS'),
        'ruta11' => _env('RUTA11_PASS')
    ],

    'inventario_user' => _env('INVENTARIO_USER'),
    'inventario_password' => _env('INVENTARIO_PASSWORD'),

    'caja_users' => [
        'admin' => 'R11adm2025x7k9',
        'cajera' => 'ruta11caja'
    ],

    'gmail_client_id' => _env('GMAIL_CLIENT_ID'),
    'gmail_client_secret' => _env('GMAIL_CLIENT_SECRET'),
    'gmail_sender_email' => _env('GMAIL_SENDER_EMAIL'),

    'external_credentials' => [
        'pedidosya' => [
            'platform' => 'PedidosYA (Gowin)',
            'email' => _env('PEDIDOSYA_EMAIL'),
            'password' => _env('PEDIDOSYA_PASSWORD')
        ],
        'instagram' => [
            'platform' => 'Instagram',
            'email' => _env('INSTAGRAM_EMAIL'),
            'password' => _env('INSTAGRAM_PASSWORD')
        ],
        'tuu_platform' => [
            'platform' => 'TUU Platform',
            'email' => _env('TUU_PLATFORM_EMAIL'),
            'password' => _env('TUU_PLATFORM_PASSWORD')
        ]
    ],

    'aws_access_key_id' => _env('AWS_ACCESS_KEY_ID'),
    'aws_secret_access_key' => _env('AWS_SECRET_ACCESS_KEY'),
    's3_bucket' => _env('S3_BUCKET'),
    's3_region' => _env('S3_REGION'),
    's3_url' => _env('S3_URL'),

    'discount_codes' => [
        'PIZZA11' => ['product_id' => 231, 'discount_percent' => 20, 'name' => 'Pizza 20% OFF', 'active' => true],
        'TENS' => ['product_id' => 213, 'discount_percent' => 30, 'name' => 'Tens 30% OFF', 'active' => true],
        'RL6' => ['type' => 'delivery', 'discount_percent' => 40, 'name' => 'Delivery 40% OFF', 'active' => true],
        'R11LOV' => ['type' => 'cart', 'discount_percent' => 10, 'name' => '10% OFF Total', 'active' => true],
        'TUAREG' => ['type' => 'cart', 'discount_percent' => 7, 'name' => '7% OFF Total', 'active' => true]
    ],

    // Telegram Bot Configuration
    'telegram_token' => _env('TELEGRAM_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE',
    'telegram_chat_id' => _env('TELEGRAM_CHAT_ID') ?: 'YOUR_CHAT_ID_HERE',
];

return $config;
