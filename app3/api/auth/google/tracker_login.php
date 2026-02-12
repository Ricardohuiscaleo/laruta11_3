<?php
session_start();

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../config.php';

// Configuración OAuth para Jobs Tracker
$client_id = $config['ruta11_tracker_client_id'];
$client_secret = $config['ruta11_tracker_client_secret'];
$redirect_uri = $config['ruta11_tracker_redirect_uri'];

// URL de autorización de Google
$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

// Guardar URL de redirección después del login
if (isset($_GET['redirect'])) {
    $_SESSION['tracker_redirect_after_login'] = $_GET['redirect'];
}

// Redirigir a Google OAuth
header('Location: ' . $auth_url);
exit();
?>