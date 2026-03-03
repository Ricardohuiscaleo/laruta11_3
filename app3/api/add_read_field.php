<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../../../config.php'
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
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

// Mapear si la columna existe (usando método compatible)
$check = $conn->query("SHOW COLUMNS FROM order_notifications LIKE 'is_read'");
if ($check->num_rows == 0) {
    $sql = "ALTER TABLE order_notifications ADD COLUMN is_read BOOLEAN DEFAULT FALSE";
    if ($conn->query($sql)) {
        echo json_encode(['success' => true, 'message' => 'Campo is_read agregado exitosamente']);
    }
    else {
        echo json_encode(['success' => false, 'error' => $conn->error]);
    }
}
else {
    echo json_encode(['success' => true, 'message' => 'Campo is_read ya existe']);
}

$conn->close();
?>