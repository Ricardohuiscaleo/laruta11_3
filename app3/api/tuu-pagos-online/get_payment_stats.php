<?php
header('Content-Type: application/json');

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
        __DIR__ . '/../../config.php',     // 2 niveles
        __DIR__ . '/../../../config.php',  // 3 niveles  
        __DIR__ . '/../../../../config.php', // 4 niveles
        __DIR__ . '/../../../../../config.php' // 5 niveles
    ];
    
    $config = null;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            $config = require_once $path;
            break;
        }
    }
    
    if (!$config) {
        throw new Exception('config.php not found');
    }
    
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    // Estadísticas generales TUU desde tuu_orders
    $general_stats_sql = "
        SELECT 
            COUNT(*) as total_transactions,
            SUM(CASE WHEN status = 'completed' THEN tuu_amount ELSE 0 END) as total_revenue,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_payments,
            AVG(CASE WHEN status = 'completed' THEN tuu_amount ELSE NULL END) as avg_transaction_amount,
            COUNT(DISTINCT user_id) as unique_customers
        FROM tuu_orders
        WHERE tuu_amount IS NOT NULL
    ";
    
    $result = mysqli_query($conn, $general_stats_sql);
    $general_stats = mysqli_fetch_assoc($result);
    
    // Estadísticas por método de pago (todos son webpay desde tuu_orders)
    $payment_method_stats_sql = "
        SELECT 
            'webpay' as payment_method,
            COUNT(*) as count,
            SUM(CASE WHEN status = 'completed' THEN tuu_amount ELSE 0 END) as revenue
        FROM tuu_orders
        WHERE tuu_amount IS NOT NULL
    ";
    
    $result = mysqli_query($conn, $payment_method_stats_sql);
    $payment_methods = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Transacciones recientes desde tuu_orders con todos los campos
    $recent_transactions_sql = "
        SELECT 
            order_number as order_reference,
            tuu_amount as amount,
            'webpay' as payment_method,
            status,
            customer_name,
            customer_phone,
            product_name,
            product_price,
            tuu_transaction_id,
            tuu_timestamp,
            tuu_message,
            tuu_account_id,
            tuu_currency,
            tuu_signature,
            table_number,
            installments_total,
            installment_current,
            installment_amount,
            created_at,
            updated_at
        FROM tuu_orders 
        WHERE tuu_amount IS NOT NULL
        ORDER BY created_at DESC 
        LIMIT 50
    ";
    
    $result = mysqli_query($conn, $recent_transactions_sql);
    $recent_transactions = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'general_stats' => $general_stats,
            'payment_methods' => $payment_methods,
            'recent_transactions' => $recent_transactions
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>