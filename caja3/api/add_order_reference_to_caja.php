<?php
// Script para agregar columna order_reference a caja_movimientos
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
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
    die(json_encode(['success' => false, 'error' => 'Config not found']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Connection failed']));
}

// Agregar columna order_reference si no existe
$sql = "ALTER TABLE caja_movimientos ADD COLUMN IF NOT EXISTS order_reference VARCHAR(50) DEFAULT NULL AFTER usuario";

try {
    $conn->query($sql);
    echo json_encode(['success' => true, 'message' => 'Columna order_reference agregada']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>
