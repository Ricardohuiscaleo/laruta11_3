<?php
session_start();
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

// Verificar autenticación Google
if (isset($_SESSION['jobs_user_id'])) {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['jobs_user_id'],
            'nombre' => $_SESSION['jobs_user_name'],
            'email' => $_SESSION['jobs_user_email'],
            'foto_perfil' => $_SESSION['jobs_user_photo']
        ]
    ]);
    exit;
} 

// Verificar autenticación manual por sesión
if (isset($_SESSION['user_id']) && isset($_SESSION['auth_type']) && $_SESSION['auth_type'] === 'manual') {
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'nombre' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'foto_perfil' => '/icon.png'
        ]
    ]);
    exit;
}

// Verificar token desde header Authorization
$headers = getallheaders();
$token = null;

if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
}

if ($token && $conn) {
    $query = "SELECT id, email, nombre FROM usuarios WHERE session_token = ? AND activo = 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $token);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'foto_perfil' => '/icon.png'
            ]
        ]);
        exit;
    }
}

echo json_encode(['authenticated' => false]);
?>