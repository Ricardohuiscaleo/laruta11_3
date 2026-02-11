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
    
    // Obtener pedidos activos (no entregados) ordenados por fecha
    // Incluye pedidos pagados Y pedidos de transferencia pendientes
    $sql = "SELECT id, order_number, user_id, customer_name, customer_phone, table_number, 
                   product_name, has_item_details, product_price, installments_total, 
                   installment_current, installment_amount, tuu_payment_request_id, 
                   tuu_idempotency_key, tuu_device_used, status, payment_status, payment_method, order_status, 
                   delivery_type, delivery_address, customer_notes, special_instructions, 
                   rider_id, estimated_delivery_time, created_at, updated_at, 
                   tuu_transaction_id, tuu_amount, tuu_timestamp, tuu_message, 
                   tuu_account_id, tuu_currency, tuu_signature, delivery_fee
            FROM tuu_orders 
            WHERE order_status NOT IN ('delivered', 'cancelled') 
            AND (payment_status = 'paid' OR (payment_method = 'transfer' AND payment_status = 'unpaid'))
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada orden, obtener sus items con información del producto
    foreach ($orders as &$order) {
        $itemsSql = "SELECT oi.*, p.name as product_name, p.image_url, p.category_id 
                     FROM tuu_order_items oi 
                     LEFT JOIN products p ON oi.product_id = p.id 
                     WHERE oi.order_id = ?";
        
        $itemsStmt = $pdo->prepare($itemsSql);
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Debug: verificar si hay pedidos cancelados
    $debugSql = "SELECT order_number, order_status FROM tuu_orders WHERE order_number = 'T11-1760992244-8330'";
    $debugStmt = $pdo->prepare($debugSql);
    $debugStmt->execute();
    $debugOrder = $debugStmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'debug_query' => $sql,
        'total_orders' => count($orders),
        'debug_cancelled_order' => $debugOrder
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>