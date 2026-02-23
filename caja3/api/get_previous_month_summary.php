<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$debug = [];
$debug[] = 'Script iniciado';

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        $debug[] = 'Config encontrado en: ' . $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado', 'debug' => $debug]);
    exit;
}

try {
    $debug[] = 'Conectando a DB: ' . $config['app_db_name'];
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $debug[] = 'Conexión exitosa';
    
    // Calcular mes anterior
    $lastMonth = date('Y-m', strtotime('-1 month'));
    $lastMonthStart = $lastMonth . '-01';
    $lastMonthEnd = date('Y-m-t', strtotime($lastMonthStart));
    $debug[] = 'Mes anterior: ' . $lastMonthStart . ' a ' . $lastMonthEnd;
    
    // Ventas totales del mes anterior desde tuu_orders
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT o.order_number) as total_orders,
            SUM(o.installment_amount) as total_sales,
            SUM(COALESCE(o.delivery_fee, 0)) as total_delivery
        FROM tuu_orders o
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.payment_status = 'paid'
        AND o.order_number NOT LIKE 'RL6-%'
    ");
    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
    $sales = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalSalesGross = floatval($sales['total_sales'] ?? 0);
    $totalDelivery = floatval($sales['total_delivery'] ?? 0);
    $totalSales = $totalSalesGross - $totalDelivery;
    $totalOrders = intval($sales['total_orders'] ?? 0);
    $avgTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;
    
    $debug[] = 'Ventas: ' . $totalSales . ' | Pedidos: ' . $totalOrders;
    
    // CMV del mes anterior desde tuu_order_items
    $stmt = $pdo->prepare("
        SELECT SUM(oi.item_cost * oi.quantity) as total_cost
        FROM tuu_order_items oi
        JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        AND o.payment_status = 'paid'
        AND o.order_number NOT LIKE 'RL6-%'
    ");
    $stmt->execute([$lastMonthStart, $lastMonthEnd]);
    $cost = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalCost = floatval($cost['total_cost'] ?? 0);
    $grossProfit = $totalSales - $totalCost;
    $marginPercent = $totalSales > 0 ? ($grossProfit / $totalSales) * 100 : 0;
    
    $debug[] = 'Costo: ' . $totalCost . ' | Margen: ' . round($marginPercent, 1) . '%';
    
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'data' => [
            'month' => $lastMonth,
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'avg_ticket' => $avgTicket,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'margin_percent' => $marginPercent
        ]
    ]);
    
} catch (Exception $e) {
    $debug[] = 'ERROR: ' . $e->getMessage();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug]);
}
?>
