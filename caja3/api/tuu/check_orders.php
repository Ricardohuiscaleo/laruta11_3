<?php
header('Content-Type: application/json');

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
    
    // Verificar si existe la tabla tuu_orders
    $stmt = $pdo->query("SHOW TABLES LIKE 'tuu_orders'");
    $table_exists = $stmt->rowCount() > 0;
    
    if (!$table_exists) {
        echo json_encode(['success' => false, 'error' => 'Tabla tuu_orders no existe']);
        exit;
    }
    
    // Contar registros en tuu_orders
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tuu_orders");
    $total_orders = $stmt->fetch()['total'];
    
    // Contar registros con customer_name
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM tuu_orders WHERE customer_name IS NOT NULL AND customer_name != ''");
    $orders_with_names = $stmt->fetch()['total'];
    
    // Obtener algunos ejemplos
    $stmt = $pdo->query("SELECT order_number, customer_name, customer_phone, tuu_amount, created_at FROM tuu_orders WHERE customer_name IS NOT NULL LIMIT 5");
    $examples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'table_exists' => $table_exists,
            'total_orders' => $total_orders,
            'orders_with_names' => $orders_with_names,
            'examples' => $examples
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>