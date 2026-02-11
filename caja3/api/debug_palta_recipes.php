<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("
        SELECT pr.product_id, p.name as producto, pr.ingredient_id, i.name as ingrediente, 
               pr.quantity, pr.unit, i.current_stock
        FROM product_recipes pr 
        JOIN products p ON pr.product_id = p.id 
        JOIN ingredients i ON pr.ingredient_id = i.id 
        WHERE i.name LIKE '%Palta%' 
        ORDER BY pr.product_id
    ");
    
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>