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
    
    // Verificar sale_id de POS
    $stmt = $pdo->query("SELECT sale_id FROM tuu_pos_transactions LIMIT 5");
    $pos_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Verificar tuu_transaction_id de orders
    $stmt = $pdo->query("SELECT tuu_transaction_id FROM tuu_orders WHERE tuu_transaction_id IS NOT NULL LIMIT 5");
    $order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Buscar coincidencias
    $matches = array_intersect($pos_ids, $order_ids);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'pos_sale_ids' => $pos_ids,
            'order_transaction_ids' => $order_ids,
            'matches' => array_values($matches),
            'can_connect' => count($matches) > 0
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>