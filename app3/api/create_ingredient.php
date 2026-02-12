<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

// Buscar config.php en múltiples niveles
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

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['name']) || empty($data['name'])) {
    die(json_encode(['success' => false, 'error' => 'Nombre del ingrediente requerido']));
}

$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión: ' . $conn->connect_error]));
}

$name = $conn->real_escape_string($data['name']);
$category = isset($data['category']) ? $conn->real_escape_string($data['category']) : null;
$unit = isset($data['unit']) ? $conn->real_escape_string($data['unit']) : 'kg';
$cost_per_unit = isset($data['cost_per_unit']) ? floatval($data['cost_per_unit']) : 0;
$current_stock = isset($data['current_stock']) ? floatval($data['current_stock']) : 0;
$min_stock_level = isset($data['min_stock_level']) ? floatval($data['min_stock_level']) : 1;
$supplier = isset($data['supplier']) ? $conn->real_escape_string($data['supplier']) : null;

$sql = "INSERT INTO ingredients (name, category, unit, cost_per_unit, current_stock, min_stock_level, supplier, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sssddds", $name, $category, $unit, $cost_per_unit, $current_stock, $min_stock_level, $supplier);

if ($stmt->execute()) {
    $id = $conn->insert_id;
    echo json_encode(['success' => true, 'message' => 'Ingrediente creado correctamente', 'id' => $id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al crear ingrediente: ' . $conn->error]);
}

$conn->close();
?>