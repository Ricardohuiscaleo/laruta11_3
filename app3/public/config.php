<?php
$config = [
    'PUBLIC_SUPABASE_URL' => getenv('PUBLIC_SUPABASE_URL'),
    'PUBLIC_SUPABASE_ANON_KEY' => getenv('PUBLIC_SUPABASE_ANON_KEY'),
    'gemini_api_key' => getenv('GEMINI_API_KEY'),
    'unsplash_access_key' => getenv('UNSPLASH_ACCESS_KEY'),
    'google_calendar_api_key' => getenv('GOOGLE_CALENDAR_API_KEY'),
    'google_client_id' => getenv('GOOGLE_CLIENT_ID'),
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET'),
    
    'booking_db_host' => getenv('BOOKING_DB_HOST'),
    'booking_db_name' => getenv('BOOKING_DB_NAME'),
    'booking_db_user' => getenv('BOOKING_DB_USER'),
    'booking_db_pass' => getenv('BOOKING_DB_PASS'),
    
    'rag_db_host' => getenv('RAG_DB_HOST'),
    'rag_db_name' => getenv('RAG_DB_NAME'),
    'rag_db_user' => getenv('RAG_DB_USER'),
    'rag_db_pass' => getenv('RAG_DB_PASS'),
    
    'ruta11game_db_host' => getenv('RUTA11GAME_DB_HOST'),
    'ruta11game_db_name' => getenv('RUTA11GAME_DB_NAME'),
    'ruta11game_db_user' => getenv('RUTA11GAME_DB_USER'),
    'ruta11game_db_pass' => getenv('RUTA11GAME_DB_PASS'),
    
    'Calcularuta11_db_host' => getenv('CALCULARUTA11_DB_HOST'),
    'Calcularuta11_db_name' => getenv('CALCULARUTA11_DB_NAME'),
    'Calcularuta11_db_user' => getenv('CALCULARUTA11_DB_USER'),
    'Calcularuta11_db_pass' => getenv('CALCULARUTA11_DB_PASS'),
    
    'ruta11_db_host' => getenv('RUTA11_DB_HOST'),
    'ruta11_db_name' => getenv('RUTA11_DB_NAME'),
    'ruta11_db_user' => getenv('RUTA11_DB_USER'),
    'ruta11_db_pass' => getenv('RUTA11_DB_PASS'),
    
    'ruta11_jobs_client_id' => getenv('RUTA11_JOBS_CLIENT_ID'),
    'ruta11_jobs_client_secret' => getenv('RUTA11_JOBS_CLIENT_SECRET'),
    'ruta11_jobs_redirect_uri' => getenv('RUTA11_JOBS_REDIRECT_URI'),
    
    'ruta11_tracker_client_id' => getenv('RUTA11_TRACKER_CLIENT_ID'),
    'ruta11_tracker_client_secret' => getenv('RUTA11_TRACKER_CLIENT_SECRET'),
    'ruta11_tracker_redirect_uri' => getenv('RUTA11_TRACKER_REDIRECT_URI'),
    
    'ruta11_google_maps_api_key' => getenv('RUTA11_GOOGLE_MAPS_API_KEY'),
    
    'gmail_client_id' => getenv('GMAIL_CLIENT_ID'),
    'gmail_client_secret' => getenv('GMAIL_CLIENT_SECRET'),
    'gmail_redirect_uri' => getenv('GMAIL_REDIRECT_URI'),
    'gmail_sender_email' => getenv('GMAIL_SENDER_EMAIL'),
    
    'tuu_api_key' => getenv('TUU_API_KEY'),
    'tuu_online_rut' => getenv('TUU_ONLINE_RUT'),
    'tuu_online_secret' => getenv('TUU_ONLINE_SECRET'),
    'tuu_online_env' => getenv('TUU_ONLINE_ENV'),
    'tuu_environment' => getenv('TUU_ENVIRONMENT'),
    'tuu_device_serial' => getenv('TUU_DEVICE_SERIAL'),
    'tuu_devices' => [
        'pos1' => [
            'serial' => '6010B232541610747',
            'name' => 'POS Principal - La Ruta 11',
            'location' => 'Mostrador Principal'
        ],
        'pos2' => [
            'serial' => '6010B232541609909',
            'name' => 'POS Secundario - La Ruta 11',
            'location' => 'Caja 2'
        ]
    ],
    
    'app_db_host' => getenv('APP_DB_HOST'),
    'app_db_name' => getenv('APP_DB_NAME'),
    'app_db_user' => getenv('APP_DB_USER'),
    'app_db_pass' => getenv('APP_DB_PASS'),
    
    'admin_users' => [
        'admin' => getenv('ADMIN_PASSWORD'),
        'ricardo' => getenv('RICARDO_PASSWORD'),
        'manager' => getenv('MANAGER_PASSWORD'),
        'ruta11' => getenv('RUTA11_PASSWORD')
    ],
    
    'inventario_user' => getenv('INVENTARIO_USER'),
    'inventario_password' => getenv('INVENTARIO_PASSWORD'),
    
    'external_credentials' => [
        'pedidosya' => [
            'platform' => 'PedidosYA (Gowin)',
            'email' => getenv('PEDIDOSYA_EMAIL'),
            'password' => getenv('PEDIDOSYA_PASSWORD')
        ],
        'instagram' => [
            'platform' => 'Instagram',
            'email' => getenv('INSTAGRAM_EMAIL'),
            'password' => getenv('INSTAGRAM_PASSWORD')
        ],
        'tuu_platform' => [
            'platform' => 'TUU Platform',
            'email' => getenv('TUU_PLATFORM_EMAIL'),
            'password' => getenv('TUU_PLATFORM_PASSWORD')
        ]
    ],
    
    'aws_access_key_id' => getenv('AWS_ACCESS_KEY_ID'),
    'aws_secret_access_key' => getenv('AWS_SECRET_ACCESS_KEY'),
    's3_bucket' => getenv('S3_BUCKET'),
    's3_region' => getenv('S3_REGION'),
    's3_url' => getenv('S3_URL'),
    
    'discount_codes' => [
        'PIZZA11' => ['product_id' => 231, 'discount_percent' => 20, 'name' => 'Pizza 20% OFF', 'active' => true],
        'TENS' => ['product_id' => 213, 'discount_percent' => 30, 'name' => 'Tens 30% OFF', 'active' => true],
        'RL6' => ['type' => 'delivery', 'discount_percent' => 40, 'name' => 'Delivery 40% OFF', 'active' => true],
        'R11LOV' => ['type' => 'cart', 'discount_percent' => 10, 'name' => '10% OFF Total', 'active' => true],
        'TUAREG' => ['type' => 'cart', 'discount_percent' => 7, 'name' => '7% OFF Total', 'active' => true]
    ]
];

return $config;
?>
