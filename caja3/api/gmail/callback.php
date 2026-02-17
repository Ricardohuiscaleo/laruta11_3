<?php
// Callback de OAuth - Recibe el código y obtiene tokens
$config = require_once __DIR__ . '/../../config.php';

if (!isset($_GET['code'])) {
    die('Error: No se recibió código de autorización');
}

$code = $_GET['code'];

// Intercambiar código por tokens
$token_url = 'https://oauth2.googleapis.com/token';
$data = [
    'code' => $code,
    'client_id' => $config['gmail_client_id'],
    'client_secret' => $config['gmail_client_secret'],
    'redirect_uri' => 'https://caja.laruta11.cl/api/gmail/callback.php',
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
curl_close($ch);

$tokens = json_decode($response, true);

if (isset($tokens['error'])) {
    die('Error al obtener tokens: ' . $tokens['error_description']);
}

// Guardar tokens con timestamp de expiración
$token_data = [
    'access_token' => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
    'expires_at' => time() + $tokens['expires_in'],
    'created_at' => date('Y-m-d H:i:s')
];

$token_file = __DIR__ . '/../../gmail_token.json';
file_put_contents($token_file, json_encode($token_data, JSON_PRETTY_PRINT));

echo '<h1>✅ Autenticación exitosa</h1>';
echo '<p>Token guardado correctamente. Ya puedes enviar emails desde caja3.</p>';
echo '<p><a href="/admin/emails">Ir al gestor de correos</a></p>';
