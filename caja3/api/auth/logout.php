<?php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

// Capturar session_id antes de destruir
$session_id = session_id();

// Destruir sesión
session_destroy();

// Eliminar explícitamente de MySQL
if ($session_id) {
    $config = require __DIR__ . '/../../config.php';
    $conn = mysqli_connect(
        $config['ruta11_db_host'],
        $config['ruta11_db_user'],
        $config['ruta11_db_pass'],
        $config['ruta11_db_name']
    );
    
    if ($conn) {
        $stmt = mysqli_prepare($conn, "DELETE FROM php_sessions WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, "s", $session_id);
        mysqli_stmt_execute($stmt);
        mysqli_close($conn);
    }
}

// Invalidar cookie
setcookie('PHPSESSID', '', time() - 3600, '/', '', true, true);

echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
?>
