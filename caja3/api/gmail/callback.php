<?php
// Callback de OAuth - Recibe el código y obtiene tokens (v2)
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

// Guardar en BD usando prepared statement
$stmt = $conn->prepare("INSERT INTO gmail_tokens (id, access_token, refresh_token, expires_at) VALUES (1, ?, ?, ?) ON DUPLICATE KEY UPDATE access_token=VALUES(access_token), refresh_token=VALUES(refresh_token), expires_at=VALUES(expires_at), updated_at=NOW()");
$stmt->bind_param('ssi', $token_data['access_token'], $token_data['refresh_token'], $token_data['expires_at']);

if ($stmt->execute()) {
    echo '<h1>✅ Autenticación exitosa</h1>';
    
    // Verificar en BD
    $result = $conn->query("SELECT id, LENGTH(access_token) as at_len, LENGTH(refresh_token) as rt_len, expires_at FROM gmail_tokens WHERE id=1");
    if ($row = $result->fetch_assoc()) {
        echo '<h2>✅ Token guardado en BD:</h2>';
        echo '<p>Access token: ' . $row['at_len'] . ' caracteres</p>';
        echo '<p>Refresh token: ' . $row['rt_len'] . ' caracteres</p>';
        echo '<p>Expira: ' . date('Y-m-d H:i:s', $row['expires_at']) . '</p>';
        
        if ($row['at_len'] > 0 && $row['rt_len'] > 0) {
            echo '<p style="color:green; font-weight:bold">🎉 TODO CORRECTO - Tokens guardados exitosamente</p>';
        } else {
            echo '<p style="color:red; font-weight:bold">⚠️ ERROR - Tokens vacíos en BD</p>';
        }
    }
    
    echo '<p><a href="/admin/emails">Ir al gestor de correos</a></p>';
} else {
    die('Error al guardar token: ' . $stmt->error);
}

$stmt->close();
$conn->close();
