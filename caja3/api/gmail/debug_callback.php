<?php
// Debug callback - Ver qué está pasando
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
    'redirect_uri' => 'https://caja.laruta11.cl/api/gmail/debug_callback.php',
    'grant_type' => 'authorization_code'
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
$response = curl_exec($ch);
curl_close($ch);

$tokens = json_decode($response, true);

echo '<h1>🔍 Debug Callback</h1>';
echo '<h2>1. Tokens recibidos de Google:</h2>';
echo '<pre>' . print_r($tokens, true) . '</pre>';

if (isset($tokens['error'])) {
    die('<p style="color:red">Error: ' . ($tokens['error_description'] ?? $tokens['error']) . '</p>');
}

if (!isset($tokens['access_token']) || !isset($tokens['refresh_token'])) {
    die('<p style="color:red">Error: No se recibieron los tokens necesarios</p>');
}

// Preparar datos
$token_data = [
    'access_token' => $tokens['access_token'],
    'refresh_token' => $tokens['refresh_token'],
    'expires_at' => time() + $tokens['expires_in']
];

echo '<h2>2. Datos a guardar:</h2>';
echo '<pre>' . print_r($token_data, true) . '</pre>';

// Conectar a BD
$db_host = $config['app_db_host'];
$db_user = $config['app_db_user'];
$db_pass = $config['app_db_pass'];
$db_name = $config['app_db_name'];

echo '<h2>3. Conexión BD:</h2>';
echo '<p>Host: ' . $db_host . '</p>';
echo '<p>User: ' . $db_user . '</p>';
echo '<p>DB: ' . $db_name . '</p>';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('<p style="color:red">Error de conexión: ' . $conn->connect_error . '</p>');
}

echo '<p style="color:green">✅ Conexión exitosa</p>';

// Preparar query
$sql = "INSERT INTO gmail_tokens (id, access_token, refresh_token, expires_at) 
        VALUES (1, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
        access_token=?, refresh_token=?, expires_at=?, updated_at=NOW()";

echo '<h2>4. Query SQL:</h2>';
echo '<pre>' . $sql . '</pre>';

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('<p style="color:red">Error al preparar: ' . $conn->error . '</p>');
}

echo '<p style="color:green">✅ Query preparada</p>';

// Bind params
$stmt->bind_param('ssissi', 
    $token_data['access_token'], 
    $token_data['refresh_token'], 
    $token_data['expires_at'],
    $token_data['access_token'], 
    $token_data['refresh_token'], 
    $token_data['expires_at']
);

echo '<h2>5. Bind params:</h2>';
echo '<p>Tipos: ssissi (6 params)</p>';
echo '<p>access_token length: ' . strlen($token_data['access_token']) . '</p>';
echo '<p>refresh_token length: ' . strlen($token_data['refresh_token']) . '</p>';
echo '<p>expires_at: ' . $token_data['expires_at'] . ' (' . date('Y-m-d H:i:s', $token_data['expires_at']) . ')</p>';

// Ejecutar
if ($stmt->execute()) {
    echo '<h2 style="color:green">✅ Ejecutado exitosamente</h2>';
    echo '<p>Affected rows: ' . $stmt->affected_rows . '</p>';
    
    // Verificar en BD
    $result = $conn->query("SELECT id, LENGTH(access_token) as at_len, LENGTH(refresh_token) as rt_len, expires_at FROM gmail_tokens WHERE id=1");
    if ($row = $result->fetch_assoc()) {
        echo '<h2>6. Verificación en BD:</h2>';
        echo '<pre>' . print_r($row, true) . '</pre>';
    }
} else {
    echo '<h2 style="color:red">❌ Error al ejecutar</h2>';
    echo '<p>Error: ' . $stmt->error . '</p>';
    echo '<p>Errno: ' . $stmt->errno . '</p>';
}

$stmt->close();
$conn->close();
