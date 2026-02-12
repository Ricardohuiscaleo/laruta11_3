<?php
session_start();

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../config.php';

// Configuración OAuth para Jobs Tracker
$client_id = $config['ruta11_tracker_client_id'];
$client_secret = $config['ruta11_tracker_client_secret'];
$redirect_uri = $config['ruta11_tracker_redirect_uri'];

if (!isset($_GET['code'])) {
    die('Error: No se recibió código de autorización');
}

$code = $_GET['code'];

// Intercambiar código por token
$token_url = 'https://oauth2.googleapis.com/token';
$token_data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'code' => $code,
    'grant_type' => 'authorization_code',
    'redirect_uri' => $redirect_uri
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

$token_response = curl_exec($ch);
curl_close($ch);

$token_info = json_decode($token_response, true);

if (!isset($token_info['access_token'])) {
    die('Error obteniendo token de acceso');
}

// Obtener información del usuario
$user_info_url = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $token_info['access_token'];
$user_response = file_get_contents($user_info_url);
$user_info = json_decode($user_response, true);

// Conectar a BD para verificar usuario autorizado
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    die('Error de conexión a base de datos');
}

mysqli_set_charset($conn, 'utf8');

// Verificar si el usuario está autorizado
$stmt = mysqli_prepare($conn, "SELECT id, nombre, role FROM tracker_authorized_users WHERE email = ? AND active = TRUE");
mysqli_stmt_bind_param($stmt, "s", $user_info['email']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$authorized_user = mysqli_fetch_assoc($result);

if (!$authorized_user) {
    mysqli_close($conn);
    die('Error: No tienes permisos para acceder al dashboard de candidatos.');
}

mysqli_close($conn);

// Guardar información del usuario en sesión
$_SESSION['tracker_user'] = [
    'id' => $user_info['id'],
    'email' => $user_info['email'],
    'nombre' => $authorized_user['nombre'], // Usar nombre de BD
    'foto_perfil' => $user_info['picture'],
    'role' => $authorized_user['role'],
    'db_id' => $authorized_user['id']
];

// Redirigir a la URL original o al dashboard
$redirect_url = $_SESSION['tracker_redirect_after_login'] ?? '/jobsTracker/';
unset($_SESSION['tracker_redirect_after_login']);

// Agregar parámetro de éxito
$redirect_url .= (strpos($redirect_url, '?') !== false ? '&' : '?') . 'login=success';

header('Location: ' . $redirect_url);
exit();
?>