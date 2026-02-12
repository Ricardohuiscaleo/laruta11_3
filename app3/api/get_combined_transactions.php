<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se encontró el archivo de configuración']);
        exit;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Obtener pedidos TUU de los últimos 30 días
    $sql = "SELECT 
        id as order_id,
        order_number,
        customer_name,
        customer_phone,
        table_number,
        product_name,
        product_price,
        installments_total,
        installment_current,
        installment_amount,
        tuu_payment_request_id,
        tuu_idempotency_key,
        tuu_device_used,
        status,
        created_at,
        updated_at
    FROM tuu_orders 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY created_at DESC
    LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar datos para el frontend
    $processedTransactions = [];
    $totalAmount = 0;
    $completedCount = 0;
    $pendingCount = 0;
    
    foreach ($orders as $order) {
        $processedTransactions[] = [
            'id' => $order['order_id'],
            'tuu_payment_id' => $order['tuu_payment_request_id'],
            'tuu_payment_request_id' => $order['tuu_payment_request_id'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'table_number' => $order['table_number'],
            'product_name' => $order['product_name'],
            'product_price' => floatval($order['product_price']),
            'installment_info' => $order['installment_current'] . '/' . $order['installments_total'],
            'amount' => floatval($order['installment_amount']),
            'device_used' => $order['tuu_device_used'],
            'status' => $order['status'],
            'created_at' => $order['created_at'],
            'payment_method' => 'Tarjeta de Débito',
            'card_type' => 'Mastercard'
        ];
        
        $totalAmount += floatval($order['installment_amount']);
        
        if ($order['status'] === 'completed') {
            $completedCount++;
        } elseif (in_array($order['status'], ['pending', 'sent_to_pos'])) {
            $pendingCount++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $processedTransactions,
            'summary' => [
                'total_transactions' => count($processedTransactions),
                'total_amount' => $totalAmount,
                'completed_transactions' => $completedCount,
                'pending_transactions' => $pendingCount,
                'failed_transactions' => 0
            ]
        ],
        'source' => 'TUU Orders Table'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>