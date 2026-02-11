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

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Contar POS
    $pos_count = 0;
    if (in_array('tuu_pos_transactions', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tuu_pos_transactions");
        $pos_count = $stmt->fetchColumn();
    }
    
    // Contar APP
    $app_count = 0;
    if (in_array('tuu_orders', $tables)) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM tuu_orders WHERE installment_amount IS NOT NULL");
        $app_count = $stmt->fetchColumn();
    }
    
    echo json_encode([
        'success' => true,
        'tables' => $tables,
        'pos_transactions' => $pos_count,
        'app_orders' => $app_count
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>