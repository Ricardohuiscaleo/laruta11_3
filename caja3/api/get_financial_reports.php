<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

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
    
    $total_gastos_fijos = 1500000;
    
    // Función para obtener gastos fijos según el mes
    $getGastosFijos = function($mes) {
        // Enero 2026 = 1.500.000, otros meses = 1.590.000
        return $mes === '2026-01' ? 1500000 : 1590000;
    };
    
    // KPIs del mes anterior (mes más reciente completo)
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    $previousMonth = clone $shiftToday;
    $previousMonth->modify('first day of last month');
    $previousMonthStr = $previousMonth->format('Y-m');
    
    $sql_current = "SELECT 
        COUNT(DISTINCT o.order_number) as pedidos,
        SUM(o.installment_amount) as suma_installment,
        SUM(o.delivery_fee) as suma_delivery
    FROM tuu_orders o
    WHERE o.payment_status = 'paid' 
    AND o.order_number NOT LIKE 'RL6-%'
    AND DATE_FORMAT(o.created_at, '%Y-%m') = '$previousMonthStr'";
    
    $stmt_current = $pdo->query($sql_current);
    $current_data = $stmt_current->fetch(PDO::FETCH_ASSOC);
    
    $ventas_actual = floatval($current_data['suma_installment'] ?? 0) - floatval($current_data['suma_delivery'] ?? 0);
    $sql_costo = "SELECT SUM(oi.item_cost * oi.quantity) as costo_real
    FROM tuu_order_items oi
    INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
    WHERE o.payment_status = 'paid' 
    AND o.order_number NOT LIKE 'RL6-%'
    AND DATE_FORMAT(o.created_at, '%Y-%m') = '$previousMonthStr'";
    
    $stmt_costo = $pdo->query($sql_costo);
    $costo_data = $stmt_costo->fetch(PDO::FETCH_ASSOC);
    
    $costo_actual = floatval($costo_data['costo_real'] ?? 0);
    $utilidad_bruta_actual = $ventas_actual - $costo_actual;
    $utilidad_neta_actual = $utilidad_bruta_actual - $total_gastos_fijos;
    $margen_actual = $ventas_actual > 0 ? ($utilidad_bruta_actual / $ventas_actual) * 100 : 0;
    $pedidos_actual = (int)($current_data['pedidos'] ?? 0);
    
    // Obtener últimos 12 meses (incluyendo mes actual)
    $sql_meses = "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as mes,
        COUNT(DISTINCT o.order_number) as pedidos,
        SUM(o.installment_amount) as ventas_bruto,
        SUM(o.delivery_fee) as delivery_fee,
        SUM(o.installment_amount) - SUM(o.delivery_fee) as ventas_neto
    FROM tuu_orders o
    WHERE o.payment_status = 'paid' 
    AND o.order_number NOT LIKE 'RL6-%'
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 12";
    
    $stmt = $pdo->query($sql_meses);
    $meses_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener costos por mes (incluyendo mes actual)
    $sql_costos = "SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') as mes,
        SUM(oi.item_cost * oi.quantity) as costo_real
    FROM tuu_order_items oi
    INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
    WHERE o.payment_status = 'paid' 
    AND o.order_number NOT LIKE 'RL6-%'
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')";
    
    $stmt_costos = $pdo->query($sql_costos);
    $costos_data = $stmt_costos->fetchAll(PDO::FETCH_ASSOC);
    $costos_map = [];
    foreach ($costos_data as $row) {
        $costos_map[$row['mes']] = floatval($row['costo_real']);
    }
    
    $rentabilidad = [];
    foreach ($meses_data as $mes) {
        $ventas_bruto = floatval($mes['ventas_bruto']);
        $delivery = floatval($mes['delivery_fee']);
        $ventas = floatval($mes['ventas_neto']);
        $costo = floatval($costos_map[$mes['mes']] ?? 0);
        $gastos = $getGastosFijos($mes['mes']);
        $utilidad_bruta = $ventas - $costo;
        $utilidad_neta = $utilidad_bruta - $gastos;
        $margen = $ventas > 0 ? ($utilidad_bruta / $ventas) * 100 : 0;
        
        $rentabilidad[] = [
            'mes' => $mes['mes'],
            'pedidos' => (int)$mes['pedidos'],
            'ventas_bruto' => $ventas_bruto,
            'delivery_fee' => $delivery,
            'ventas' => $ventas,
            'costo_real' => $costo,
            'utilidad_bruta' => $utilidad_bruta,
            'gastos_fijos' => $gastos,
            'utilidad_neta' => $utilidad_neta,
            'margen_pct' => round($margen, 1),
            'estado' => $utilidad_neta >= 0 ? '✅ GANANCIA' : '❌ PÉRDIDA'
        ];
    }
    
    // Productos TOP 10
    $sql_top = "SELECT 
        oi.product_name as producto,
        COUNT(*) as veces_vendido,
        SUM(oi.subtotal) as ingresos,
        AVG(oi.subtotal) as precio_promedio
    FROM tuu_order_items oi
    INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
    WHERE o.payment_status = 'paid' AND o.order_number NOT LIKE 'RL6-%' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) AND oi.item_type = 'product'
    GROUP BY oi.product_name
    ORDER BY ingresos DESC
    LIMIT 10";
    
    $stmt = $pdo->query($sql_top);
    $productos_top = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Productos MENOS vendidos
    $sql_menos = "SELECT 
        oi.product_name as producto,
        COUNT(*) as veces_vendido,
        SUM(oi.subtotal) as ingresos,
        AVG(oi.subtotal) as precio_promedio
    FROM tuu_order_items oi
    INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
    WHERE o.payment_status = 'paid' AND o.order_number NOT LIKE 'RL6-%' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) AND oi.item_type = 'product'
    GROUP BY oi.product_name
    HAVING COUNT(*) > 0
    ORDER BY veces_vendido ASC
    LIMIT 5";
    
    $stmt = $pdo->query($sql_menos);
    $productos_menos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Canales de venta
    $sql_canales = "SELECT 
        COALESCE(o.payment_method, 'Sin especificar') as canal,
        COUNT(DISTINCT o.order_number) as pedidos,
        SUM(o.installment_amount) - SUM(COALESCE(o.delivery_fee, 0)) as ventas,
        AVG(o.installment_amount) as ticket_promedio
    FROM tuu_orders o
    WHERE o.payment_status = 'paid' AND o.order_number NOT LIKE 'RL6-%' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
    GROUP BY o.payment_method
    ORDER BY ventas DESC";
    
    $stmt = $pdo->query($sql_canales);
    $canales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'mes_actual' => [
            'ventas' => $ventas_actual,
            'utilidad_neta' => $utilidad_neta_actual,
            'margen_pct' => round($margen_actual, 1),
            'pedidos' => $pedidos_actual
        ],
        'totales' => [
            'ventas_total' => array_sum(array_map(fn($m) => $m['ventas'], $rentabilidad)),
            'utilidad_total' => array_sum(array_map(fn($m) => $m['utilidad_neta'], $rentabilidad)),
            'pedidos_total' => array_sum(array_map(fn($m) => $m['pedidos'], $rentabilidad)),
            'crecimiento_ventas' => count($rentabilidad) >= 2 ? round((($rentabilidad[0]['ventas'] - $rentabilidad[1]['ventas']) / $rentabilidad[1]['ventas']) * 100, 1) : 0
        ],
        'rentabilidad' => $rentabilidad,
        'productos_top' => $productos_top,
        'productos_menos' => $productos_menos,
        'canales' => $canales,
        'gastos_fijos' => $total_gastos_fijos
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
