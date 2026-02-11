<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Debug: Obtener TODOS los pedidos de transferencia
    $sql = "SELECT id, order_number, payment_method, payment_status, order_status, created_at 
            FROM tuu_orders 
            WHERE payment_method = 'transfer'
            ORDER BY created_at DESC 
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $transferOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Obtener pedidos que deberían aparecer en comandas
    $sql2 = "SELECT id, order_number, payment_method, payment_status, order_status, created_at 
             FROM tuu_orders 
             WHERE order_status != 'delivered' 
             AND (payment_status = 'paid' OR (payment_method = 'transfer' AND payment_status = 'unpaid'))
             ORDER BY created_at DESC";
    
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute();
    $comandasOrders = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'all_transfer_orders' => $transferOrders,
        'comandas_orders' => $comandasOrders,
        'query_used' => $sql2
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>