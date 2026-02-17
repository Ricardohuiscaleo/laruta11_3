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

// Guardar en volumen persistente (PRIORITARIO)
$token_file = '/var/www/html/api/gmail/gmail_token.json';
$backup_file = __DIR__ . '/gmail_token.json';

$results = [];

// Intentar crear directorio si no existe
if (!is_dir(dirname($token_file))) {
    $mkdir_result = @mkdir(dirname($token_file), 0777, true);
    $results[] = 'Crear directorio: ' . ($mkdir_result ? 'OK' : 'FALLÓ');
}

// Intentar guardar en volumen persistente
$persistent_result = @file_put_contents($token_file, json_encode($token_data, JSON_PRETTY_PRINT));
$results[] = 'Guardar en volumen persistente (' . $token_file . '): ' . ($persistent_result !== false ? 'OK (' . $persistent_result . ' bytes)' : 'FALLÓ');

// Guardar copia local como backup
$backup_result = @file_put_contents($backup_file, json_encode($token_data, JSON_PRETTY_PRINT));
$results[] = 'Guardar backup local (' . $backup_file . '): ' . ($backup_result !== false ? 'OK (' . $backup_result . ' bytes)' : 'FALLÓ');

// Verificar permisos
$results[] = 'Directorio volumen existe: ' . (is_dir(dirname($token_file)) ? 'SÍ' : 'NO');
$results[] = 'Directorio volumen escribible: ' . (is_writable(dirname($token_file)) ? 'SÍ' : 'NO');
$results[] = 'Archivo volumen existe: ' . (file_exists($token_file) ? 'SÍ' : 'NO');
$results[] = 'Archivo backup existe: ' . (file_exists($backup_file) ? 'SÍ' : 'NO');

echo '<h1>✅ Autenticación exitosa</h1>';
echo '<h2>📊 Resultados del guardado:</h2>';
echo '<ul>';
foreach ($results as $result) {
    echo '<li>' . $result . '</li>';
}
echo '</ul>';
echo '<p><a href="/admin/emails">Ir al gestor de correos</a></p>';
echo '<p><a href="/api/gmail/check_token.php">Verificar token</a></p>';
