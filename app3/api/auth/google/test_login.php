<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST LOGIN ===\n\n";

echo "1. Intentando cargar config...\n";
try {
    $config = require_once __DIR__ . '/../../../config.php';
    echo "2. Config cargado OK\n";
    echo "3. CLIENT_ID: " . ($config['ruta11_google_client_id'] ?? 'NOT SET') . "\n";
    echo "4. REDIRECT_URI: " . ($config['ruta11_google_redirect_uri'] ?? 'NOT SET') . "\n";
    
    if (!isset($config['ruta11_google_client_id'])) {
        throw new Exception('CLIENT_ID no configurado');
    }
    
    $auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $config['ruta11_google_client_id'],
        'redirect_uri' => $config['ruta11_google_redirect_uri'],
        'scope' => 'openid email profile',
        'response_type' => 'code',
        'access_type' => 'online'
    ]);
    
    echo "\n5. URL generada OK\n";
    echo "6. Redirigiendo a Google...\n\n";
    echo "URL: " . $auth_url . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
