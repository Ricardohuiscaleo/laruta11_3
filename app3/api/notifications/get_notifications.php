<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
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

$query = "SELECT * FROM user_notifications WHERE user_id = $user_id ORDER BY fecha_creacion DESC LIMIT 50";
$result = mysqli_query($user_conn, $query);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = $row;
}

// Contar no leídas
$unread_query = "SELECT COUNT(*) as unread_count FROM user_notifications WHERE user_id = $user_id AND leida = FALSE";
$unread_result = mysqli_query($user_conn, $unread_query);
$unread_count = mysqli_fetch_assoc($unread_result)['unread_count'];

mysqli_close($user_conn);

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => $unread_count
]);
?>