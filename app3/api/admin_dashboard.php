<?php
$config = require_once __DIR__ . '/../../../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );

    $stats = [];

    // Solo productos (tabla que existe)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE is_active = 1");
    $stats['total_products'] = $stmt->fetch()['total'];

    // Stock bajo (tabla que existe)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['low_stock_products'] = $result ? $result['total'] : 0;

    // Órdenes y pagos = 0 (tablas no existen)
    $stats['orders_today'] = 0;
    $stats['sales_today'] = 0;
    $stats['pending_payments'] = 0;

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'recent_orders' => [],
        'top_products' => []
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}