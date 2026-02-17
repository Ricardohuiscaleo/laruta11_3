<?php
// Iniciar autenticación OAuth de Gmail
$config = require_once __DIR__ . '/../../config.php';

$client_id = $config['gmail_client_id'];
$redirect_uri = 'https://caja.laruta11.cl/api/gmail/callback.php';

$auth_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/gmail.send',
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

header('Location: ' . $auth_url);
exit;
