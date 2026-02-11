<?php
header('Content-Type: application/json');

try {
    $config = require_once __DIR__ . '/../../config.php';
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

    // Datos de transacciones TUU
    $transactions_sql = "
        SELECT 
            order_reference,
            amount,
            payment_method,
            status,
            created_at,
            completed_at,
            tuu_transaction_id,
            customer_name,
            customer_email,
            customer_phone
        FROM tuu_pagos_online 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ";
    
    $stmt = mysqli_prepare($conn, $transactions_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $transactions = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    
    // Estadísticas de pagos TUU
    $stats_sql = "
        SELECT 
            COUNT(*) as total_tuu_orders,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_tuu_spent,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_tuu_order,
            COUNT(CASE WHEN payment_method = 'webpay' THEN 1 END) as webpay_payments,
            COUNT(CASE WHEN payment_method = 'redcompra' THEN 1 END) as redcompra_payments,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
            COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
        FROM tuu_pagos_online 
        WHERE user_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $stats = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    // Actividad en la app
    $activity_sql = "
        SELECT 
            total_sessions,
            total_time_seconds,
            ultimo_acceso,
            fecha_registro
        FROM usuarios 
        WHERE id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $activity_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $activity = mysqli_stmt_get_result($stmt)->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'purchase_stats' => $stats,
            'app_activity' => $activity
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>