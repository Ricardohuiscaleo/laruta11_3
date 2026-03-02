<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Ventas del mes
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as pedidos_mes,
            COALESCE(SUM(product_price), 0) as ventas_mes
        FROM tuu_orders 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND payment_status = 'paid'
    ");
    $ventas = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pedidos hoy
    $stmt = $pdo->query("
        SELECT COUNT(*) as pedidos_hoy
        FROM tuu_orders 
        WHERE DATE(created_at) = CURRENT_DATE()
    ");
    $hoy = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inventario
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as items_activos,
            COALESCE(SUM(current_stock * cost_per_unit), 0) as total_inventario
        FROM ingredients 
        WHERE is_active = 1
    ");
    $inventario = $stmt->fetch(PDO::FETCH_ASSOC);

    // Items críticos
    $stmt = $pdo->query("
        SELECT name as nombre, current_stock as stock_actual, unit as unidad
        FROM ingredients 
        WHERE current_stock <= min_stock_level 
        AND is_active = 1
        ORDER BY current_stock ASC
        LIMIT 10
    ");
    $items_criticos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ticket promedio
    $ticket_promedio = $ventas['pedidos_mes'] > 0 
        ? $ventas['ventas_mes'] / $ventas['pedidos_mes'] 
        : 0;

    echo json_encode([
        'ventas_mes' => $ventas['ventas_mes'],
        'ventas_mes_formateado' => '$' . number_format($ventas['ventas_mes'], 0, ',', '.'),
        'pedidos_mes' => $ventas['pedidos_mes'],
        'pedidos_hoy' => $hoy['pedidos_hoy'],
        'items_activos' => $inventario['items_activos'],
        'total_inventario' => $inventario['total_inventario'],
        'total_inventario_formateado' => '$' . number_format($inventario['total_inventario'], 0, ',', '.'),
        'ticket_promedio' => $ticket_promedio,
        'ticket_promedio_formateado' => '$' . number_format($ticket_promedio, 0, ',', '.'),
        'items_criticos_data' => $items_criticos
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
