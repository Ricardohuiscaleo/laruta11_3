<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

header('Content-Type: application/json');

$debug = [
    'cookies' => $_COOKIE,
    'has_auth_token' => isset($_COOKIE['auth_token']),
    'auth_token' => $_COOKIE['auth_token'] ?? null,
    'db_connected' => $conn ? true : false
];

if (isset($_COOKIE['auth_token']) && $conn) {
    $token = $_COOKIE['auth_token'];
    $query = "SELECT id, email, nombre, session_token FROM usuarios WHERE session_token = ? AND activo = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    $debug['token_query'] = $query;
    $debug['user_found'] = $user ? true : false;
    $debug['user_data'] = $user;
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>