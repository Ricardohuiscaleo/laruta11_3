<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // Consumo por ingrediente
    $ingredients_sql = "
        SELECT 
            i.name,
            SUM(ABS(t.quantity)) as total_consumido,
            t.unit,
            COUNT(*) as num_transacciones,
            i.current_stock as stock_actual
        FROM inventory_transactions t
        JOIN ingredients i ON t.ingredient_id = i.id
        WHERE t.transaction_type = 'sale'
          AND DATE(t.created_at) >= ?
          AND DATE(t.created_at) <= ?
        GROUP BY i.id, i.name, t.unit, i.current_stock
        ORDER BY total_consumido DESC
    ";
    
    $ing_stmt = $pdo->prepare($ingredients_sql);
    $ing_stmt->execute([$start_date, $end_date]);
    $ingredients = $ing_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Consumo por producto
    $products_sql = "
        SELECT 
            p.name,
            SUM(ABS(t.quantity)) as total_vendido,
            COUNT(*) as num_transacciones,
            p.stock_quantity as stock_actual
        FROM inventory_transactions t
        JOIN products p ON t.product_id = p.id
        WHERE t.transaction_type = 'sale'
          AND DATE(t.created_at) >= ?
          AND DATE(t.created_at) <= ?
        GROUP BY p.id, p.name, p.stock_quantity
        ORDER BY total_vendido DESC
    ";
    
    $prod_stmt = $pdo->prepare($products_sql);
    $prod_stmt->execute([$start_date, $end_date]);
    $products = $prod_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'period' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ],
        'ingredients' => $ingredients,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
