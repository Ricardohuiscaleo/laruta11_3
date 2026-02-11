<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    // Ver TODAS las órdenes recientes
    $all = $pdo->query("SELECT id, order_number, order_status, payment_status, payment_method, created_at FROM tuu_orders ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    // Ver órdenes con order_status = 'pending'
    $pending = $pdo->query("SELECT id, order_number, order_status, payment_status FROM tuu_orders WHERE order_status = 'pending'")->fetchAll(PDO::FETCH_ASSOC);
    
    // Ver órdenes que NO son delivered ni cancelled
    $active = $pdo->query("SELECT id, order_number, order_status, payment_status FROM tuu_orders WHERE order_status NOT IN ('delivered', 'cancelled')")->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'all_recent' => $all,
        'pending_orders' => $pending,
        'active_orders' => $active,
        'db_name' => $config['app_db_name']
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
