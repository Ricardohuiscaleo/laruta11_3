<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

// Conectar a BD correcta (u958525313_app)
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

$user_id = $_SESSION['user']['id'];

try {
    // Obtener pedidos TUU del usuario (1 por fila como solicitado)
    $orders_sql = "
        SELECT 
            tp.order_reference,
            tp.amount,
            tp.payment_method,
            tp.status,
            tp.customer_name,
            tp.customer_phone,
            tp.created_at,
            tp.completed_at,
            CASE 
                WHEN tp.status = 'completed' THEN '✅ Entregado'
                WHEN tp.status = 'pending' THEN '⏱️ Pendiente'
                WHEN tp.status = 'failed' THEN '❌ Fallido'
                WHEN tp.status = 'cancelled' THEN '🚫 Cancelado'
                ELSE '📦 Procesando'
            END as status_display
        FROM tuu_pagos_online tp
        WHERE tp.user_id = ?
        ORDER BY tp.created_at DESC
        LIMIT 20
    ";
    
    $stmt = mysqli_prepare($conn, $orders_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $orders = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    // Estadísticas del usuario
    $stats_sql = "
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_spent,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as avg_order_amount,
            MAX(created_at) as last_order_date
        FROM tuu_pagos_online 
        WHERE user_id = ?
    ";
    
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $stats = mysqli_fetch_assoc($result);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>