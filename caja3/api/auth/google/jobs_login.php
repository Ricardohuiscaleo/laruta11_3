<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../../config.php';

// Configuración desde config.php
$client_id = $config['ruta11_jobs_client_id'];
$client_secret = $config['ruta11_jobs_client_secret'];
$redirect_uri = $config['ruta11_jobs_redirect_uri'];

// Redirigir a Google OAuth
$auth_url = "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'openid email profile',
    'response_type' => 'code',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => $_GET['redirect'] ?? '/jobs/'
]);

header('Location: ' . $auth_url);
exit();
?>