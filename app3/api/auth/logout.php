<?php
// Usar configuración de sesión MySQL
require_once __DIR__ . '/../session_config.php';

// Obtener session_id antes de destruir
$session_id = session_id();

// Destruir sesión (llama a MySQLSessionHandler::destroy())
session_destroy();

// Asegurar que la fila se borró de MySQL
if ($session_id) {
    $config = require __DIR__ . '/../../config.php';
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );
    
    if ($conn) {
        $stmt = mysqli_prepare($conn, "DELETE FROM php_sessions WHERE session_id = ?");
        mysqli_stmt_bind_param($stmt, 's', $session_id);
        mysqli_stmt_execute($stmt);
        mysqli_close($conn);
    }
}

// Invalidar cookie PHPSESSID
setcookie('PHPSESSID', '', time() - 3600, '/', '', true, true);

header('Location: https://app.laruta11.cl/?logout=success');
exit();
?>