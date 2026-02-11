<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión']));
}

$id = $_GET['id'] ?? '';

if (!$id) {
    die(json_encode(['success' => false, 'error' => 'ID requerido']));
}

$stmt = $conn->prepare("SELECT * FROM food_trucks WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$truck = $result->fetch_assoc();

if ($truck) {
    echo json_encode(['success' => true, 'truck' => $truck]);
} else {
    echo json_encode(['success' => false, 'error' => 'Food truck no encontrado']);
}

$conn->close();
?>