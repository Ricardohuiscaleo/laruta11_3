<?php
/**
 * Script de Reconciliación de Inventario
 * Detecta órdenes sin transacciones de inventario registradas
 * Ejecutar semanalmente o bajo demanda
 */

header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
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
    
    // Parámetros
    $days_back = $_GET['days'] ?? 30;
    $date_from = date('Y-m-d', strtotime("-{$days_back} days"));
    
    // Buscar órdenes completadas sin transacciones de inventario
    $query = "
        SELECT 
            o.order_number,
            o.created_at,
            o.customer_name,
            o.payment_method,
            o.status,
            o.payment_status,
            COUNT(oi.id) as items_count,
            COUNT(DISTINCT it.id) as transactions_count
        FROM tuu_orders o
        LEFT JOIN tuu_order_items oi ON o.order_number = oi.order_reference
        LEFT JOIN inventory_transactions it ON o.order_number = it.order_reference
        WHERE o.created_at >= ?
        AND o.payment_status = 'paid'
        AND o.status IN ('completed', 'delivered')
        GROUP BY o.order_number
        HAVING transactions_count = 0 AND items_count > 0
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$date_from]);
    $orders_without_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Estadísticas por método de pago
    $stats_query = "
        SELECT 
            payment_method,
            COUNT(*) as total_orders,
            SUM(CASE WHEN has_transactions = 0 THEN 1 ELSE 0 END) as orders_without_transactions,
            ROUND(SUM(CASE WHEN has_transactions = 0 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as percentage_missing
        FROM (
            SELECT 
                o.order_number,
                o.payment_method,
                CASE WHEN COUNT(DISTINCT it.id) > 0 THEN 1 ELSE 0 END as has_transactions
            FROM tuu_orders o
            LEFT JOIN tuu_order_items oi ON o.order_number = oi.order_reference
            LEFT JOIN inventory_transactions it ON o.order_number = it.order_reference
            WHERE o.created_at >= ?
            AND o.payment_status = 'paid'
            AND o.status IN ('completed', 'delivered')
            GROUP BY o.order_number, o.payment_method
        ) as subquery
        GROUP BY payment_method
        ORDER BY orders_without_transactions DESC
    ";
    
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$date_from]);
    $stats_by_method = $stats_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Alertas críticas (más del 10% sin transacciones)
    $critical_alerts = array_filter($stats_by_method, function($stat) {
        return $stat['percentage_missing'] > 10;
    });
    
    $response = [
        'success' => true,
        'period' => [
            'from' => $date_from,
            'to' => date('Y-m-d'),
            'days' => $days_back
        ],
        'summary' => [
            'total_orders_without_transactions' => count($orders_without_transactions),
            'has_critical_alerts' => !empty($critical_alerts)
        ],
        'stats_by_payment_method' => $stats_by_method,
        'critical_alerts' => $critical_alerts,
        'orders_without_transactions' => $orders_without_transactions
    ];
    
    // Log si hay alertas críticas
    if (!empty($critical_alerts)) {
        error_log("ALERTA CRÍTICA: Reconciliación de inventario detectó problemas en métodos de pago: " . 
                 json_encode(array_column($critical_alerts, 'payment_method')));
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
