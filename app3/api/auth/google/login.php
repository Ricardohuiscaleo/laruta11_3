<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    // Cargar config desde raíz
    $config = require_once __DIR__ . '/../../../config.php';

    // Verificar que las configuraciones existan
    if (!isset($config['ruta11_google_client_id']) || !isset($config['ruta11_google_redirect_uri'])) {
        throw new Exception('Configuración de Google OAuth no encontrada');
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
} catch (Exception $e) {
    header('Location: https://app.laruta11.cl/?login=error&msg=' . urlencode($e->getMessage()));
    exit();
}
?>