<?php
session_start();
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../../../config.php';

$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

// Verificar datos en usuarios
$users_query = "SELECT id, nombre, kanban_status FROM usuarios WHERE kanban_status IS NOT NULL LIMIT 10";
$users_result = mysqli_query($conn, $users_query);
$users = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $users[] = $row;
}

// Verificar datos en kanban_cards
$cards_query = "SELECT kc.id, kc.user_id, kc.column_id, u.nombre, u.kanban_status FROM kanban_cards kc LEFT JOIN usuarios u ON kc.user_id = u.id LIMIT 10";
$cards_result = mysqli_query($conn, $cards_query);
$cards = [];
while ($row = mysqli_fetch_assoc($cards_result)) {
    $cards[] = $row;
}

echo json_encode([
    'success' => true,
    'users_with_status' => $users,
    'kanban_cards' => $cards
]);

mysqli_close($conn);
?>