<?php
// Callback simplificado para debug
$config = require_once __DIR__ . '/../../../../../config.php';

// Log todo en un archivo
$log_file = __DIR__ . '/oauth_debug.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Callback iniciado\n", FILE_APPEND);

if (!isset($_GET['code'])) {
    file_put_contents($log_file, "ERROR: No code received\n", FILE_APPEND);
    die('No code');
}

file_put_contents($log_file, "Code received: " . substr($_GET['code'], 0, 20) . "...\n", FILE_APPEND);

// Intercambiar código por token
$token_data = [
    'client_id' => $config['ruta11_google_client_id'],
    'client_secret' => $config['ruta11_google_client_secret'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'],
    'grant_type' => 'authorization_code',
    'code' => $_GET['code']
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$token_response = curl_exec($ch);
$curl_error = curl_error($ch);
curl_close($ch);

file_put_contents($log_file, "Token response: " . $token_response . "\n", FILE_APPEND);
file_put_contents($log_file, "cURL error: " . $curl_error . "\n", FILE_APPEND);

$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    file_put_contents($log_file, "ERROR: No access token. Full response: " . print_r($token_info, true) . "\n", FILE_APPEND);
    echo "<h1>Debug Info</h1>";
    echo "<p>Token Response: " . htmlspecialchars($token_response) . "</p>";
    echo "<p>cURL Error: " . htmlspecialchars($curl_error) . "</p>";
    echo "<p>Client ID: " . htmlspecialchars($config['ruta11_google_client_id']) . "</p>";
    echo "<p>Redirect URI: " . htmlspecialchars($config['ruta11_google_redirect_uri']) . "</p>";
    die();
}

// Obtener info del usuario
$user_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
$user_response = file_get_contents($user_url);
$user_info = json_decode($user_response, true);

file_put_contents($log_file, "User info: " . json_encode($user_info) . "\n", FILE_APPEND);

// Conectar a BD
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    file_put_contents($log_file, "ERROR: DB connection failed\n", FILE_APPEND);
    die('DB error');
}

// Insertar usuario
$google_id = $user_info['id'];
$email = $user_info['email'];
$nombre = $user_info['name'];
$foto_perfil = $user_info['picture'];

$sql = "INSERT INTO usuarios (google_id, email, nombre, foto_perfil, fecha_registro) VALUES ('$google_id', '$email', '$nombre', '$foto_perfil', NOW())";
$result = mysqli_query($conn, $sql);

if ($result) {
    file_put_contents($log_file, "SUCCESS: User inserted with ID " . mysqli_insert_id($conn) . "\n", FILE_APPEND);
    header('Location: https://app.laruta11.cl/?login=success&debug=1');
} else {
    file_put_contents($log_file, "ERROR: Insert failed - " . mysqli_error($conn) . "\n", FILE_APPEND);
    header('Location: https://app.laruta11.cl/?login=error&msg=db_insert_failed');
}

mysqli_close($conn);
?>