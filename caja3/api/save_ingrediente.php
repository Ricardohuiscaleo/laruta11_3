<?php
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
    
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    
    $id = $input['id'] ?? null;
    $name = $input['name'] ?? '';
    $category = $input['category'] ?? '';
    $unit = $input['unit'] ?? 'kg';
    $cost_per_unit = $input['cost_per_unit'] ?? 0;
    $current_stock = $input['current_stock'] ?? 0;
    $min_stock_level = $input['min_stock_level'] ?? 1;
    $supplier = $input['supplier'] ?? '';
    $is_active = $input['is_active'] ?? 1;
    
    if (empty($name)) {
        throw new Exception('Nombre de ingrediente requerido');
    }
    
    if ($id) {
        $stmt = $pdo->prepare(
            "UPDATE ingredients SET name=?, category=?, unit=?, cost_per_unit=?, 
             current_stock=?, min_stock_level=?, supplier=?, is_active=? WHERE id=?"
        );
        $stmt->execute([$name, $category, $unit, $cost_per_unit, $current_stock, $min_stock_level, $supplier, $is_active, $id]);
        echo json_encode(['success' => true, 'id' => $id, 'action' => 'updated']);
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO ingredients (name, category, unit, cost_per_unit, current_stock, min_stock_level, supplier, is_active) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$name, $category, $unit, $cost_per_unit, $current_stock, $min_stock_level, $supplier, $is_active]);
        echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'action' => 'created']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>