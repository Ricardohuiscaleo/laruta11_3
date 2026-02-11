<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../../config.php';

// Verificar que las configuraciones existan
if (!isset($config['ruta11_google_client_id']) || !isset($config['ruta11_google_redirect_uri'])) {
    die('Error: Configuración de Google OAuth no encontrada');
}

// URL de autorización de Google
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'],
    'scope' => 'openid email profile',
    'response_type' => 'code',
    'access_type' => 'online'
]);

// Redirigir a Google
header('Location: ' . $auth_url);
exit();
?>