<?php
// Cargar variables de entorno
require_once __DIR__ . '/load-env.php';

$config = [
    'google_maps_api_key' => getenv('GOOGLE_MAPS_API_KEY'),
    'whatsapp_api_token' => getenv('WHATSAPP_API_TOKEN') ?: '',
    'instagram_api_key' => getenv('INSTAGRAM_API_KEY') ?: '',
    'facebook_api_key' => getenv('FACEBOOK_API_KEY') ?: '',
    
    'aws_access_key_id' => getenv('AWS_ACCESS_KEY_ID'),
    'aws_secret_access_key' => getenv('AWS_SECRET_ACCESS_KEY'),
    'aws_region' => getenv('AWS_REGION') ?: 'us-east-1',
    's3_bucket' => getenv('S3_BUCKET'),
    's3_url' => getenv('S3_URL'),
    
    'db_host' => getenv('DB_HOST'),
    'db_name' => getenv('DB_NAME'),
    'db_user' => getenv('DB_USER'),
    'db_pass' => getenv('DB_PASS'),
    
    'app_url' => getenv('APP_URL') ?: 'https://laruta11.cl',
    'app_env' => getenv('APP_ENV') ?: 'production',
    'debug' => getenv('DEBUG') === 'true',
    'logo_url' => 'https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg',
    'favicon_url' => 'https://laruta11-images.s3.amazonaws.com/menu/1755571382_test.jpg',
    
    'smtp_host' => getenv('SMTP_HOST') ?: '',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_pass' => getenv('SMTP_PASS') ?: '',
    'contact_email' => 'hola@laruta11.cl',
    
    'default_location' => [
        'lat' => -33.4489,
        'lng' => -70.6693
    ],
    'business_hours' => [
        'monday' => ['11:00', '21:00'],
        'tuesday' => ['11:00', '21:00'],
        'wednesday' => ['11:00', '21:00'],
        'thursday' => ['11:00', '21:00'],
        'friday' => ['11:00', '21:00'],
        'saturday' => ['10:00', '22:00'],
        'sunday' => ['12:00', '20:00']
    ],
    
    'PUBLIC_SUPABASE_URL' => getenv('PUBLIC_SUPABASE_URL'),
    'PUBLIC_SUPABASE_ANON_KEY' => getenv('PUBLIC_SUPABASE_ANON_KEY'),
    'gemini_api_key' => getenv('GEMINI_API_KEY'),
    'unsplash_access_key' => getenv('UNSPLASH_ACCESS_KEY'),
    'google_calendar_api_key' => getenv('GOOGLE_CALENDAR_API_KEY'),
    'google_client_id' => getenv('GOOGLE_CLIENT_ID'),
    'google_client_secret' => getenv('GOOGLE_CLIENT_SECRET')
];

return $config;
?>
