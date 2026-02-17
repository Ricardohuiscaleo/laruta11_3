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
    die('Error al obtener tokens: ' . ($tokens['error_description'] ?? $tokens['error']));
}

if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
    die('Error: No se recibieron los tokens necesarios. Respuesta: ' . json_encode($tokens));
}

// Guardar tokens con timestamp de expiración
$token_data = [
    'access_token' => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
    'expires_at' => time() + $tokens['expires_in'],
    'created_at' => date('Y-m-d H:i:s')
];

// Guardar en BASE DE DATOS (PERSISTENTE)
$db_host = $config['app_db_host'];
$db_user = $config['app_db_user'];
$db_pass = $config['app_db_pass'];
$db_name = $config['app_db_name'];

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('Error de conexión a BD: ' . $conn->connect_error);
}

$stmt = $conn->prepare("INSERT INTO gmail_tokens (id, access_token, refresh_token, expires_at) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE access_token=?, refresh_token=?, expires_at=?, updated_at=NOW()");
$stmt->bind_param('ssissi', 
    $token_data['access_token'], 
    $token_data['refresh_token'], 
    $token_data['expires_at'],
    $token_data['access_token'], 
    $token_data['refresh_token'], 
    $token_data['expires_at']
);

if ($stmt->execute()) {
    echo '<h1>✅ Autenticación exitosa</h1>';
    echo '<p>Token guardado en base de datos. Persistirá para siempre.</p>';
    echo '<p><a href="/admin/emails">Ir al gestor de correos</a></p>';
} else {
    die('Error al guardar token: ' . $stmt->error);
}

$stmt->close();
$conn->close();
