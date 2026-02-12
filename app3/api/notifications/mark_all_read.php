<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Conectar a BD correcta (u958525313_app)
$user_conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);
$user_id = $_SESSION['user']['id'];

$query = "UPDATE user_notifications SET leida = TRUE, fecha_leida = NOW() 
          WHERE user_id = $user_id AND leida = FALSE";

if (mysqli_query($user_conn, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Error marcando todas las notificaciones como leídas']);
}

mysqli_close($user_conn);
?>