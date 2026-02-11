<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $unit = $_POST['unit'] ?? 'kg';
    $cost_per_unit = $_POST['cost_per_unit'] ?? 0;
    $current_stock = $_POST['current_stock'] ?? 0;
    $min_stock_level = $_POST['min_stock_level'] ?? 1;
    $supplier = $_POST['supplier'] ?? '';
    $is_active = $_POST['is_active'] ?? 1;
    
    if (empty($name)) {
        throw new Exception('Nombre de ingrediente requerido');
    }
    
    $stmt = $pdo->prepare(
        "INSERT INTO ingredients (name, category, unit, cost_per_unit, current_stock, min_stock_level, supplier, is_active) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    
    $stmt->execute([
        $name, $category, $unit, $cost_per_unit, 
        $current_stock, $min_stock_level, $supplier, $is_active
    ]);
    
    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>