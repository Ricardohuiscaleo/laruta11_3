<?php
header('Content-Type: application/json');

$configPaths = ['../config.php', '../../config.php', '../../../config.php', '../../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'No se pudo encontrar config.php']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $pdo->prepare("
        SELECT 
            id,
            order_number,
            customer_name,
            customer_phone,
            product_name,
            COALESCE(tuu_amount, product_price) as amount,
            COALESCE(payment_status, 
                CASE 
                    WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL AND tuu_transaction_id != 'N/A' THEN 'paid'
                    WHEN tuu_transaction_id IS NULL OR tuu_transaction_id = 'N/A' THEN 'unpaid'
                    ELSE 'pending_payment'
                END
            ) as payment_status,
            COALESCE(order_status, 
                CASE 
                    WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL AND tuu_transaction_id != 'N/A' THEN 'sent_to_kitchen'
                    ELSE 'pending'
                END
            ) as order_status,
            COALESCE(delivery_type, 'pickup') as delivery_type,
            delivery_address,
            created_at,
            'webpay' as payment_method
        FROM tuu_orders 
        WHERE COALESCE(payment_status, 
            CASE 
                WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL AND tuu_transaction_id != 'N/A' THEN 'paid'
                WHEN tuu_transaction_id IS NULL OR tuu_transaction_id = 'N/A' THEN 'unpaid'
                ELSE 'pending_payment'
            END
        ) = 'paid'
        AND COALESCE(order_status, 
            CASE 
                WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL AND tuu_transaction_id != 'N/A' THEN 'sent_to_kitchen'
                ELSE 'pending'
            END
        ) IN ('sent_to_kitchen', 'preparing', 'ready', 'out_for_delivery', 'delivered')
        ORDER BY 
            CASE COALESCE(order_status, 
                CASE 
                    WHEN tuu_message = 'Transaccion aprobada' AND tuu_transaction_id IS NOT NULL AND tuu_transaction_id != 'N/A' THEN 'sent_to_kitchen'
                    ELSE 'pending'
                END
            )
                WHEN 'sent_to_kitchen' THEN 1
                WHEN 'preparing' THEN 2
                WHEN 'ready' THEN 3
                WHEN 'out_for_delivery' THEN 4
                WHEN 'delivered' THEN 5
            END,
            created_at ASC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error al obtener pedidos']);
}
?>