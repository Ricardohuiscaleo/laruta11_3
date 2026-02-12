<?php
header('Content-Type: application/json');
echo json_encode([
    'RUTA11_GOOGLE_CLIENT_ID' => getenv('RUTA11_GOOGLE_CLIENT_ID') ?: 'NOT SET',
    'RUTA11_GOOGLE_CLIENT_SECRET' => getenv('RUTA11_GOOGLE_CLIENT_SECRET') ? 'SET' : 'NOT SET',
    'config_exists' => file_exists(__DIR__ . '/../config.php') ? 'YES' : 'NO',
    'load_env_exists' => file_exists(__DIR__ . '/../load-env.php') ? 'YES' : 'NO'
], JSON_PRETTY_PRINT);
