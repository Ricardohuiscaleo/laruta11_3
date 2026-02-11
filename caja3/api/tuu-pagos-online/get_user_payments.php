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
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('ID de usuario requerido');
    }
    
    $conn = mysqli_connect(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );

    // Historial de pagos TUU
    $payments_sql = "
        SELECT 
            order_reference,
            amount,
            payment_method,
            status,
            created_at,
            completed_at,
            tuu_transaction_id,
            transbank_token,
            customer_name,
            customer_email,
            customer_phone
        FROM tuu_pagos_online 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ";
    
    $stmt = mysqli_prepare($conn, $payments_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $payments = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    
    // Estadísticas TUU
    $stats_sql = "
        SELECT 
            COUNT(*) as total_tuu_orders,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_tuu_spent,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_tuu_order,
            COUNT(CASE WHEN payment_method = 'webpay' THEN 1 END) as webpay_count,
            COUNT(CASE WHEN payment_method = 'redcompra' THEN 1 END) as redcompra_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
            MAX(created_at) as last_payment_date
        FROM tuu_pagos_online 
        WHERE user_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $stats = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'payments' => $payments,
            'tuu_stats' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>