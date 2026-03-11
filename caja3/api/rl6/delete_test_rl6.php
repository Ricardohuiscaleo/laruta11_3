<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
];
$config = null;
foreach ($config_paths as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

$user_id = (int)($_POST['user_id'] ?? 0);
if (!$user_id) { echo json_encode(['success' => false, 'error' => 'user_id requerido']); exit; }

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if ($conn->connect_error) { echo json_encode(['success' => false, 'error' => 'DB error']); exit; }
$conn->set_charset('utf8mb4');

// Solo eliminar si el email es de prueba (seguridad)
$stmt = $conn->prepare("DELETE FROM usuarios WHERE id = ? AND email LIKE 'test_rl6_%@test.cl'");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$deleted = $stmt->affected_rows;
$stmt->close();
$conn->close();

if ($deleted > 0) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'No se encontró el registro de prueba (solo se pueden eliminar usuarios test_rl6_*@test.cl)']);
}
