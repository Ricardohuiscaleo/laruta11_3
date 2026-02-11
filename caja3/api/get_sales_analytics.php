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
    
    $period = $_GET['period'] ?? 'day';
    $debug[] = 'Período solicitado: ' . $period;
    
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    
    // LÓGICA DE TURNO para 'day' y 'month'
    if ($period === 'day') {
        $shiftStartDate = $now->format('Y-m-d');
        if ($currentHour >= 0 && $currentHour < 4) {
            $shiftStartDate = date('Y-m-d', strtotime($shiftStartDate . ' -1 day'));
        }
        
        $start_date_chile = $shiftStartDate . ' 17:00:00';
        $end_date_chile = date('Y-m-d', strtotime($shiftStartDate . ' +1 day')) . ' 04:00:00';
        
        $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
        $today = $end_date;
        
        $debug[] = 'TURNO: ' . $start_date_chile . ' a ' . $end_date_chile;
    } elseif ($period === 'month') {
        // Lógica de turnos para mes completo
        $shiftToday = clone $now;
        if ($currentHour >= 0 && $currentHour < 4) {
            $shiftToday->modify('-1 day');
        }
        
        $currentYear = $shiftToday->format('Y');
        $currentMonth = $shiftToday->format('m');
        
        // Primer turno: 17:00 del día 1
        $firstShiftStart = "$currentYear-$currentMonth-01 17:00:00";
        $start_date = date('Y-m-d H:i:s', strtotime($firstShiftStart . ' +3 hours'));
        
        // Último turno: 04:00 del día siguiente al último día del mes
        $endOfMonth = new DateTime("$currentYear-$currentMonth-01");
        $endOfMonth->modify('last day of this month');
        $lastDay = $endOfMonth->format('Y-m-d');
        $dayAfter = date('Y-m-d', strtotime($lastDay . ' +1 day'));
        $lastShiftEnd = "$dayAfter 04:00:00";
        $today = date('Y-m-d H:i:s', strtotime($lastShiftEnd . ' +3 hours'));
        
        $debug[] = 'MES TURNOS: ' . $firstShiftStart . ' a ' . $lastShiftEnd;
    } else {
        $today = date('Y-m-d');
        switch ($period) {
            case 'week':
                $start_date = date('Y-m-d', strtotime('-6 days'));
                break;
            case 'year':
                $start_date = date('Y-01-01');
                break;
            case 'all':
                $firstSaleQuery = "SELECT MIN(DATE(o.created_at)) as first_sale FROM tuu_orders o";
                $firstSaleStmt = $pdo->query($firstSaleQuery);
                $firstSaleData = $firstSaleStmt->fetch(PDO::FETCH_ASSOC);
                $start_date = $firstSaleData['first_sale'] ?? date('Y-m-01');
                $debug[] = 'Primera venta registrada: ' . $start_date;
                break;
            default:
                $start_date = $today;
        }
    }
    $debug[] = 'Rango: ' . $start_date . ' a ' . $today;
    
    // Verificar columnas de la tabla
    $columnsQuery = "SHOW COLUMNS FROM tuu_order_items";
    $columnsStmt = $pdo->query($columnsQuery);
    $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
    $debug[] = 'Columnas en tuu_order_items: ' . implode(', ', $columns);
    
    // Obtener productos vendidos
    if ($period === 'day' || $period === 'month') {
        // Para turno/mes: usar timestamp completo
        $sql = "
            SELECT 
                oi.product_name,
                oi.product_id,
                SUM(oi.quantity) as total_quantity,
                COUNT(DISTINCT oi.order_reference) as order_count,
                SUM(oi.subtotal) as total_revenue,
                p.cost_price as unit_cost,
                p.price as sale_price
            FROM tuu_order_items oi
            INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.created_at >= ? AND o.created_at < ?
            AND o.payment_status = 'paid'
            GROUP BY oi.product_id, oi.product_name
            ORDER BY total_quantity DESC
        ";
    } else {
        // Para otros períodos: usar DATE()
        $sql = "
            SELECT 
                oi.product_name,
                oi.product_id,
                SUM(oi.quantity) as total_quantity,
                COUNT(DISTINCT oi.order_reference) as order_count,
                SUM(oi.subtotal) as total_revenue,
                p.cost_price as unit_cost,
                p.price as sale_price
            FROM tuu_order_items oi
            INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
            AND o.payment_status = 'paid'
            GROUP BY oi.product_id, oi.product_name
            ORDER BY total_quantity DESC
        ";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $today]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = 'Productos encontrados: ' . count($products);
    
    // Para 'year', calcular días desde la primera venta real, no desde enero
    if ($period === 'year' && count($products) > 0) {
        // Obtener fecha de la primera venta del año
        $firstSaleQuery = "SELECT MIN(DATE(o.created_at)) as first_sale 
                          FROM tuu_orders o 
                          WHERE YEAR(o.created_at) = YEAR(CURDATE())";
        $firstSaleStmt = $pdo->query($firstSaleQuery);
        $firstSaleData = $firstSaleStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($firstSaleData && $firstSaleData['first_sale']) {
            $start_date = $firstSaleData['first_sale'];
            $debug[] = 'Primera venta del año: ' . $start_date;
        }
    }
    
    // Calcular promedios correctamente
    if ($period === 'day') {
        // Para turno: siempre es 1 día
        $days_in_period = 1;
    } else {
        $days_in_period = max(1, (strtotime($today) - strtotime($start_date)) / 86400 + 1);
    }
    $weeks_in_period = max(1, $days_in_period / 7);
    $debug[] = 'Días en período: ' . $days_in_period;
    
    $products_with_avg = array_map(function($p) use ($days_in_period, $weeks_in_period, &$debug) {
        $avg_per_day = $p['total_quantity'] / $days_in_period;
        $avg_per_week = $avg_per_day * 7;
        
        // Costo unitario del producto
        $unit_cost = floatval($p['unit_cost'] ?? 0);
        // Costo total = costo unitario × cantidad vendida
        $total_cost = $unit_cost * floatval($p['total_quantity']);
        
        // Debug para primer producto
        if (count($debug) < 15) {
            $debug[] = "Producto: {$p['product_name']} | Precio venta: {$p['sale_price']} | Costo unit: {$unit_cost} | Cant: {$p['total_quantity']} | Costo total: {$total_cost}";
        }
        
        return array_merge($p, [
            'avg_per_day' => round($avg_per_day, 1),
            'avg_per_week' => round($avg_per_week, 1),
            'unit_cost' => $unit_cost,
            'total_cost' => $total_cost
        ]);
    }, $products);
    
    // Ventas por día (últimos 30 días para gráfico)
    $sql_daily = "
        SELECT 
            DATE(o.created_at) as sale_date,
            oi.product_name,
            SUM(oi.quantity) as quantity
        FROM tuu_order_items oi
        INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE DATE(o.created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(o.created_at), oi.product_name
        ORDER BY sale_date DESC, quantity DESC
    ";
    
    $stmt = $pdo->prepare($sql_daily);
    $stmt->execute();
    $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $debug[] = 'Ventas diarias encontradas: ' . count($daily_sales);
    
    // Top 10 productos
    $top_products = array_slice($products_with_avg, 0, 10);
    
    // Contar órdenes únicas (no sumar order_count de productos)
    if ($period === 'day' || $period === 'month') {
        $orders_sql = "SELECT COUNT(DISTINCT o.order_number) as unique_orders
                       FROM tuu_orders o
                       WHERE o.created_at >= ? AND o.created_at < ?
                       AND o.payment_status = 'paid'";
    } else {
        $orders_sql = "SELECT COUNT(DISTINCT o.order_number) as unique_orders
                       FROM tuu_orders o
                       WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
                       AND o.payment_status = 'paid'";
    }
    
    $orders_stmt = $pdo->prepare($orders_sql);
    $orders_stmt->execute([$start_date, $today]);
    $orders_result = $orders_stmt->fetch(PDO::FETCH_ASSOC);
    $total_orders = intval($orders_result['unique_orders'] ?? 0);
    
    // Calcular KPIs totales desde ÓRDENES (no desde items)
    if ($period === 'day' || $period === 'month') {
        $revenue_sql = "SELECT SUM(o.installment_amount) as total_revenue
                        FROM tuu_orders o
                        WHERE o.created_at >= ? AND o.created_at < ?
                        AND o.payment_status = 'paid'";
    } else {
        $revenue_sql = "SELECT SUM(o.installment_amount) as total_revenue
                        FROM tuu_orders o
                        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
                        AND o.payment_status = 'paid'";
    }
    
    $revenue_stmt = $pdo->prepare($revenue_sql);
    $revenue_stmt->execute([$start_date, $today]);
    $revenue_result = $revenue_stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = floatval($revenue_result['total_revenue'] ?? 0);
    
    // Calcular costo total desde productos
    $total_cost_sum = 0;
    foreach ($products_with_avg as $product) {
        $total_cost_sum += floatval($product['total_cost']);
    }
    
    $total_profit = 0;
    
    // Calcular total de delivery
    if ($period === 'day' || $period === 'month') {
        $delivery_sql = "SELECT SUM(o.delivery_fee) as total_delivery
                         FROM tuu_orders o
                         WHERE o.created_at >= ? AND o.created_at < ?
                         AND o.payment_status = 'paid'";
    } else {
        $delivery_sql = "SELECT SUM(o.delivery_fee) as total_delivery
                         FROM tuu_orders o
                         WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
                         AND o.payment_status = 'paid'";
    }
    
    $delivery_stmt = $pdo->prepare($delivery_sql);
    $delivery_stmt->execute([$start_date, $today]);
    $delivery_result = $delivery_stmt->fetch(PDO::FETCH_ASSOC);
    $total_delivery = floatval($delivery_result['total_delivery'] ?? 0);
    
    // Restar delivery del revenue total
    $total_revenue_net = $total_revenue - $total_delivery;
    
    $total_profit = $total_revenue_net - $total_cost_sum;
    $avg_margin = $total_revenue_net > 0 ? ($total_profit / $total_revenue_net) * 100 : 0;
    $avg_ticket = $total_orders > 0 ? $total_revenue_net / $total_orders : 0;
    
    $summary_kpis = [
        'total_orders' => $total_orders,
        'total_revenue' => $total_revenue_net,
        'total_delivery' => $total_delivery,
        'total_cost' => $total_cost_sum,
        'total_profit' => $total_profit,
        'avg_margin' => round($avg_margin, 1),
        'avg_ticket' => round($avg_ticket, 0)
    ];
    
    $debug[] = 'KPIs: Pedidos únicos=' . $total_orders . ' Venta=' . $total_revenue . ' Utilidad=' . $total_profit . ' Margen=' . round($avg_margin, 1) . '%';
    
    // Resumen por método de pago (calcular costo desde items con subquery)
    if ($period === 'day' || $period === 'month') {
        $payment_sql = "SELECT 
                o.payment_method,
                COUNT(DISTINCT o.order_number) as order_count,
                SUM(o.installment_amount) as total_sales,
                SUM(COALESCE(o.delivery_fee, 0)) as total_delivery,
                SUM((
                    SELECT SUM(oi.item_cost * oi.quantity)
                    FROM tuu_order_items oi
                    WHERE oi.order_reference = o.order_number
                )) as total_cost
            FROM tuu_orders o
            WHERE o.created_at >= ? AND o.created_at < ?
            AND o.payment_status = 'paid'
            GROUP BY o.payment_method";
    } else {
        $payment_sql = "SELECT 
                o.payment_method,
                COUNT(DISTINCT o.order_number) as order_count,
                SUM(o.installment_amount) as total_sales,
                SUM(COALESCE(o.delivery_fee, 0)) as total_delivery,
                SUM((
                    SELECT SUM(oi.item_cost * oi.quantity)
                    FROM tuu_order_items oi
                    WHERE oi.order_reference = o.order_number
                )) as total_cost
            FROM tuu_orders o
            WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
            AND o.payment_status = 'paid'
            GROUP BY o.payment_method";
    }
    
    $payment_stmt = $pdo->prepare($payment_sql);
    $payment_stmt->execute([$start_date, $today]);
    $payment_methods = $payment_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $payment_summary = [];
    foreach ($payment_methods as $pm) {
        $sales_gross = floatval($pm['total_sales']);
        $delivery = floatval($pm['total_delivery']);
        $sales_net = $sales_gross - $delivery;
        $cost = floatval($pm['total_cost']);
        $profit = $sales_net - $cost;
        $margin = $sales_net > 0 ? ($profit / $sales_net) * 100 : 0;
        $avg_ticket = $pm['order_count'] > 0 ? $sales_net / $pm['order_count'] : 0;
        
        $payment_summary[] = [
            'method' => $pm['payment_method'],
            'order_count' => (int)$pm['order_count'],
            'total_sales' => $sales_net,
            'total_cost' => $cost,
            'profit' => $profit,
            'margin_percent' => round($margin, 1),
            'avg_ticket' => $avg_ticket
        ];
    }
    
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'data' => [
            'products' => $products_with_avg,
            'top_products' => $top_products,
            'daily_sales' => $daily_sales,
            'summary_kpis' => $summary_kpis,
            'period' => [
                'type' => $period,
                'start_date' => $start_date,
                'end_date' => $today,
                'days' => $days_in_period
            ],
            'payment_summary' => $payment_summary
        ]
    ]);
    
} catch (Exception $e) {
    $debug[] = 'ERROR: ' . $e->getMessage();
    $debug[] = 'Trace: ' . $e->getTraceAsString();
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'debug' => $debug]);
}
?>
