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

$sql = "
CREATE TABLE IF NOT EXISTS order_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    order_number VARCHAR(50) NOT NULL,
    customer_name VARCHAR(100),
    customer_phone VARCHAR(20),
    status VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_id (order_id),
    INDEX idx_created_at (created_at)
)";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Tabla order_notifications creada exitosamente']);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$conn->close();
?>