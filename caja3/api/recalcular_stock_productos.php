<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
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
    
    $stmt = $pdo->prepare("
        UPDATE products p 
        SET stock_quantity = (
            SELECT COALESCE(
                FLOOR(MIN(
                    CASE 
                        WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity
                        ELSE i.current_stock / pr.quantity
                    END
                )), 0
            )
            FROM product_recipes pr
            JOIN ingredients i ON pr.ingredient_id = i.id
            WHERE pr.product_id = p.id 
            AND i.is_active = 1
            AND i.current_stock > 0
        )
        WHERE p.id IN (
            SELECT DISTINCT product_id 
            FROM product_recipes
        )
    ");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Stock recalculado para {$affected} productos",
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>