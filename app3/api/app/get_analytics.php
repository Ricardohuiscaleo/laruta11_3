<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

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
        throw new Exception('Config file not found in paths: ' . implode(', ', $config_paths));
    }
    // Verificar que existan las claves de configuración
    if (!isset($config['app_db_host']) || !isset($config['app_db_name']) || !isset($config['app_db_user']) || !isset($config['app_db_pass'])) {
        throw new Exception('Missing database configuration keys');
    }
    
    // Usar base de datos app
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Métricas de hoy
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $week_ago = date('Y-m-d', strtotime('-7 days'));
    $month_ago = date('Y-m-d', strtotime('-30 days'));

    // Visitas únicas hoy
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as unique_visitors FROM site_visits WHERE visit_date = ?");
    $stmt->execute([$today]);
    $visitors_today = $stmt->fetchColumn() ?: 0;

    // Visitas únicas esta semana
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as unique_visitors FROM site_visits WHERE visit_date >= ?");
    $stmt->execute([$week_ago]);
    $visitors_week = $stmt->fetchColumn() ?: 0;

    // Visitas únicas este mes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT ip_address) as unique_visitors FROM site_visits WHERE visit_date >= ?");
    $stmt->execute([$month_ago]);
    $visitors_month = $stmt->fetchColumn() ?: 0;

    // Total usuarios registrados (usar columnas que probablemente existan)
    $total_users = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM app_users");
        $total_users = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        // Si falla, usar 0
        $total_users = 0;
    }

    // Nuevos usuarios hoy (usar created_at si existe)
    $new_users_today = 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $new_users_today = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        // Si falla con created_at, intentar registration_date
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_users WHERE DATE(registration_date) = ?");
            $stmt->execute([$today]);
            $new_users_today = $stmt->fetchColumn() ?: 0;
        } catch (Exception $e2) {
            $new_users_today = 0;
        }
    }

    // Ventas del día (usar columnas que probablemente existan)
    $sales_today = ['orders' => 0, 'revenue' => 0];
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue FROM user_orders WHERE DATE(created_at) = ?");
        $stmt->execute([$today]);
        $sales_today = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si falla, intentar con otras columnas comunes
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) as orders, COALESCE(SUM(total_amount), 0) as revenue FROM user_orders WHERE DATE(order_date) = ?");
            $stmt->execute([$today]);
            $sales_today = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e2) {
            $sales_today = ['orders' => 0, 'revenue' => 0];
        }
    }

    // Total productos
    $total_products = 0;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM products");
        $total_products = $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) {
        $total_products = 0;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'visitors' => [
                'today' => (int)$visitors_today,
                'week' => (int)$visitors_week,
                'month' => (int)$visitors_month
            ],
            'users' => [
                'total' => (int)$total_users,
                'new_today' => (int)$new_users_today
            ],
            'sales' => [
                'orders_today' => (int)($sales_today['orders'] ?? 0),
                'revenue_today' => (float)($sales_today['revenue'] ?? 0)
            ],
            'products' => [
                'total' => (int)$total_products
            ]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
?>