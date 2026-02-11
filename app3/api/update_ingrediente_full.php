<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Config file not found']);
    exit;
}

try {
    $id = $_POST['id'] ?? null;
    $name = $_POST['name'] ?? null;
    $category = $_POST['category'] ?? null;
    $unit = $_POST['unit'] ?? null;
    $cost_per_unit = $_POST['cost_per_unit'] ?? 0;
    $current_stock = $_POST['current_stock'] ?? 0;
    $min_stock_level = $_POST['min_stock_level'] ?? 0;
    $supplier = $_POST['supplier'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;

    if (!$id || !$name) {
        throw new Exception('ID y nombre requeridos');
    }

    $stmt = $pdo->prepare("
        UPDATE ingredients 
        SET name = ?, category = ?, unit = ?, cost_per_unit = ?, current_stock = ?, 
            min_stock_level = ?, supplier = ?, is_active = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $name, $category, $unit, $cost_per_unit, $current_stock, 
        $min_stock_level, $supplier, $is_active, $id
    ]);

    echo json_encode(['success' => true, 'message' => 'Ingrediente actualizado correctamente']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>