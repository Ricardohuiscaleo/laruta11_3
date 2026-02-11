<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config_paths = [
    __DIR__ . '/config.php',
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
    
    $order_reference = $_GET['order_reference'] ?? null;
    
    if (!$order_reference) {
        throw new Exception('order_reference requerido');
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            it.*,
            COALESCE(i.name, p.name) as item_name,
            COALESCE(i.current_stock, p.stock_quantity) as current_stock,
            COALESCE(i.unit, 'unit') as unit,
            CASE 
                WHEN it.ingredient_id IS NOT NULL THEN 'ingredient'
                ELSE 'product'
            END as item_type
        FROM inventory_transactions it
        LEFT JOIN ingredients i ON it.ingredient_id = i.id
        LEFT JOIN products p ON it.product_id = p.id
        WHERE it.order_reference = ?
        ORDER BY it.created_at ASC
    ");
    
    $stmt->execute([$order_reference]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
