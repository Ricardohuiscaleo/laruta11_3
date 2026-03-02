<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
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

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    $daysInMonth = (int)$now->format('t');
    $daysPassed = (int)$now->format('j');

    // Ajuste de turno (si es antes de las 4 AM, se considera el día anterior)
    if ($currentHour >= 0 && $currentHour < 4) {
        $daysPassed = max(1, $daysPassed - 1);
    }

    // 1. RESUMEN VENTAS MES ACTUAL (Netas de Delivery)
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT order_number) as total_orders,
            COALESCE(SUM(installment_amount), 0) as total_revenue_gross,
            COALESCE(SUM(delivery_fee), 0) as total_delivery
        FROM tuu_orders 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND payment_status = 'paid'
    ");
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenue_net = $sales['total_revenue_gross'] - $sales['total_delivery'];
    $orders = (int)$sales['total_orders'];

    // 2. COSTO DE VENTAS (CMV)
    $stmt = $pdo->query("
        SELECT SUM(oi.item_cost * oi.quantity) as total_cost
        FROM tuu_order_items oi
        INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE MONTH(o.created_at) = MONTH(CURRENT_DATE())
        AND YEAR(o.created_at) = YEAR(CURRENT_DATE())
        AND o.payment_status = 'paid'
    ");
    $costs = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_cost = (float)$costs['total_cost'];

    // 3. MÉTRICAS FINANCIERAS
    $profit = $revenue_net - $total_cost;
    $margin_percent = $revenue_net > 0 ? ($profit / $revenue_net) : 0.441; // Default 44.1%
    $avg_ticket = $orders > 0 ? ($revenue_net / $orders) : 0;

    // 4. PUNTO DE EQUILIBRIO
    $MONTHLY_SALARIES = 1590000;
    $breakeven_goal = $margin_percent > 0 ? ($MONTHLY_SALARIES / $margin_percent) : 0;
    $safe_target = $breakeven_goal * 1.05; // 5% Colchón

    $daily_goal = $safe_target / $daysInMonth;
    $daily_average = $revenue_net / $daysPassed;
    $expected_to_date = $daily_goal * $daysPassed;

    $progression_percent = $expected_to_date > 0 ? ($revenue_net / $expected_to_date) : 0;
    $monthly_completion = $safe_target > 0 ? ($revenue_net / $safe_target) : 0;

    // 5. COMPARATIVA MES ANTERIOR
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(installment_amount - delivery_fee), 0) as prev_revenue_net
        FROM tuu_orders 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE() - INTERVAL 1 MONTH)
        AND YEAR(created_at) = YEAR(CURRENT_DATE() - INTERVAL 1 MONTH)
        AND payment_status = 'paid'
    ");
    $prev_sales = $stmt->fetch(PDO::FETCH_ASSOC);
    $prev_revenue = (float)$prev_sales['prev_revenue_net'];
    $growth_percent = $prev_revenue > 0 ? (($revenue_net - $prev_revenue) / $prev_revenue) : 0;

    // 6. ITEMS CRÍTICOS
    $stmt = $pdo->query("
        SELECT name, current_stock, unit, min_stock_level
        FROM ingredients 
        WHERE current_stock <= min_stock_level 
        AND is_active = 1
        ORDER BY (current_stock / min_stock_level) ASC
        LIMIT 10
    ");
    $critical_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 7. VENTAS HOY
    $stmt = $pdo->query("
        SELECT COUNT(*) as orders_today, COALESCE(SUM(installment_amount - delivery_fee), 0) as revenue_today
        FROM tuu_orders 
        WHERE DATE(created_at) = CURRENT_DATE()
        AND payment_status = 'paid'
    ");
    $today_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'timestamp' => $now->format('Y-m-d H:i:s'),
        'metrics' => [
            'sales' => [
                'current_net' => $revenue_net,
                'current_gross' => (float)$sales['total_revenue_gross'],
                'delivery_total' => (float)$sales['total_delivery'],
                'orders_count' => $orders,
                'avg_ticket' => (float)$avg_ticket,
                'today_net' => (float)$today_stats['revenue_today'],
                'today_count' => (int)$today_stats['orders_today']
            ],
            'financial' => [
                'total_cost' => $total_cost,
                'profit' => $profit,
                'margin_percent' => round($margin_percent * 100, 1),
                'growth_percent' => round($growth_percent * 100, 1)
            ],
            'goals' => [
                'salaries' => $MONTHLY_SALARIES,
                'breakeven_sales' => round($breakeven_goal),
                'safe_target' => round($safe_target),
                'daily_goal' => round($daily_goal),
                'daily_average' => round($daily_average),
                'expected_to_date' => round($expected_to_date),
                'progression_percent' => round($progression_percent * 100, 1),
                'monthly_completion' => round($monthly_completion * 100, 1),
                'days_passed' => $daysPassed,
                'days_total' => $daysInMonth
            ]
        ],
        'inventory' => [
            'critical_count' => count($critical_items),
            'items' => $critical_items
        ]
    ]);

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}