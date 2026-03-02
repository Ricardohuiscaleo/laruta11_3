<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
set_time_limit(120);

// Load config
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php'
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}
if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    $currentDay = (int)$now->format('d');
    $daysInMonth = (int)$now->format('t');

    // Shift logic (17:30 - 04:00)
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    $currentYear = $shiftToday->format('Y');
    $currentMonth = $shiftToday->format('m');
    $daysPassed = (int)$shiftToday->format('d');

    $firstShiftStart = "$currentYear-$currentMonth-01 17:00:00";
    $firstShiftStartUTC = gmdate('Y-m-d H:i:s', strtotime($firstShiftStart . ' +3 hours'));
    $dayAfterLast = date('Y-m-d', strtotime($shiftToday->format('Y-m-t') . ' +1 day'));
    $lastShiftEndUTC = gmdate('Y-m-d H:i:s', strtotime($dayAfterLast . ' 04:00:00 +3 hours'));

    // --- 1. SALES & TICKETS ---
    $stmtSales = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders, 
            COALESCE(SUM(installment_amount), 0) as total_revenue_gross,
            COALESCE(SUM(delivery_fee), 0) as total_delivery
        FROM tuu_orders 
        WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid'
    ");
    $stmtSales->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $sales = $stmtSales->fetch(PDO::FETCH_ASSOC);

    $revenue_net = (float)$sales['total_revenue_gross'] - (float)$sales['total_delivery'];
    $orders = (int)$sales['total_orders'];
    $avg_ticket = $orders > 0 ? ($revenue_net / $orders) : 0;

    // Top Item by Revenue
    $stmtTopItem = $pdo->prepare("
        SELECT product_name, SUM(subtotal) as revenue 
        FROM tuu_order_items oi 
        JOIN tuu_orders o ON oi.order_reference = o.order_number 
        WHERE o.created_at >= ? AND o.created_at < ? AND o.payment_status = 'paid' 
        GROUP BY product_name ORDER BY revenue DESC LIMIT 1
    ");
    $stmtTopItem->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $topItem = $stmtTopItem->fetch(PDO::FETCH_ASSOC);

    // --- 2. PURCHASES & COSTS (CMV) ---
    $stmtPurchases = $pdo->prepare("SELECT SUM(monto_total) as total_compras, COUNT(*) as num_compras FROM compras WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ?");
    $stmtPurchases->execute(["$currentYear-$currentMonth"]);
    $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);
    $total_cost = (float)($purchasesData['total_compras'] ?? 0);

    // Top Provider
    $stmtProvider = $pdo->prepare("SELECT proveedor FROM compras WHERE DATE_FORMAT(fecha_compra, '%Y-%m') = ? AND proveedor IS NOT NULL GROUP BY proveedor ORDER BY COUNT(*) DESC LIMIT 1");
    $stmtProvider->execute(["$currentYear-$currentMonth"]);
    $topProvider = $stmtProvider->fetch(PDO::FETCH_ASSOC);

    // --- 3. INVENTORY STATUS ---
    $stmtInv = $pdo->query("SELECT SUM(current_stock * cost_per_unit) as value, COUNT(*) as count FROM ingredients WHERE is_active = 1");
    $invData = $stmtInv->fetch(PDO::FETCH_ASSOC);
    $invValue = (float)$invData['value'];

    // Stagnant item (highest value in stock)
    $stmtStagnant = $pdo->query("SELECT name, (current_stock * cost_per_unit) as stock_value FROM ingredients WHERE is_active = 1 ORDER BY stock_value DESC LIMIT 1");
    $stagnantData = $stmtStagnant->fetch(PDO::FETCH_ASSOC);

    // --- 4. BREAKEVEN & SALARIES ---
    $stmtSueldos = $pdo->query("SELECT SUM(sueldo_base_cajero + sueldo_base_planchero + sueldo_base_admin) as total FROM personal WHERE rol != 'seguridad'");
    $sueldosData = $stmtSueldos->fetch(PDO::FETCH_ASSOC);
    $MONTHLY_SALARIES = (float)($sueldosData['total'] ?? 1500000);

    $profit = $revenue_net - $total_cost;
    $margin_percent = $revenue_net > 0 ? ($profit / $revenue_net) : 0.441;

    $breakeven_goal = $margin_percent > 0 ? $MONTHLY_SALARIES / $margin_percent : 0;
    $safe_target = $breakeven_goal * 1.05;
    $missingToBreakeven = max(0, $breakeven_goal - $revenue_net);
    $liquidity = $revenue_net - $total_cost - $MONTHLY_SALARIES;

    // --- 5. GOALS & PROJECTIONS ---
    $daily_goal = $safe_target / $daysInMonth;
    $daily_average = $daysPassed > 0 ? $revenue_net / $daysPassed : 0;
    $expected_to_date = $daily_goal * $daysPassed;
    $progression_percent = $expected_to_date > 0 ? ($revenue_net / $expected_to_date) : 0;
    $monthly_completion = $safe_target > 0 ? ($revenue_net / $safe_target) : 0;

    // --- 6. COMPARATIVA MES ANTERIOR ---
    $prevMonth = date('Y-m', strtotime($currentYear . '-' . $currentMonth . '-01 -1 month'));
    $stmtPrevSales = $pdo->prepare("SELECT SUM(installment_amount - delivery_fee) as total FROM tuu_orders WHERE DATE_FORMAT(DATE_SUB(created_at, INTERVAL 3 HOUR), '%Y-%m') = ? AND payment_status = 'paid'");
    $stmtPrevSales->execute([$prevMonth]);
    $prev_revenue = (float)($stmtPrevSales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    $growth_percent = $prev_revenue > 0 ? (($revenue_net - $prev_revenue) / $prev_revenue) : 0;

    // --- 7. PAYMENT METHODS ---
    $stmtPM = $pdo->prepare("SELECT payment_method, SUM(installment_amount) as total FROM tuu_orders WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid' GROUP BY payment_method ORDER BY total DESC");
    $stmtPM->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $paymentMethods = $stmtPM->fetchAll(PDO::FETCH_ASSOC);

    // --- 8. SALES BY ADDRESS (Top 10) ---
    $stmtAddr = $pdo->prepare("SELECT delivery_address as address, SUM(installment_amount) as total, COUNT(*) as count FROM tuu_orders WHERE created_at >= ? AND created_at < ? AND payment_status = 'paid' AND delivery_address IS NOT NULL AND delivery_address != '' GROUP BY address ORDER BY total DESC LIMIT 10");
    $stmtAddr->execute([$firstShiftStartUTC, $lastShiftEndUTC]);
    $addresses = $stmtAddr->fetchAll(PDO::FETCH_ASSOC);

    // --- 9. ITEMS CRÍTICOS (Stock bajo) ---
    $stmtCrit = $pdo->query("SELECT name, current_stock, unit, min_stock_level FROM ingredients WHERE current_stock <= min_stock_level AND is_active = 1 ORDER BY (current_stock / min_stock_level) ASC LIMIT 10");
    $critical_items = $stmtCrit->fetchAll(PDO::FETCH_ASSOC);

    // --- 10. VENTAS HOY ---
    $stmtToday = $pdo->query("SELECT COUNT(*) as orders_today, COALESCE(SUM(installment_amount - delivery_fee), 0) as revenue_today FROM tuu_orders WHERE DATE(DATE_SUB(created_at, INTERVAL 3 HOUR)) = CURRENT_DATE() AND payment_status = 'paid'");
    $today_stats = $stmtToday->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'timestamp' => $now->format('Y-m-d H:i:s'),
        'metrics' => [
            'ventas' => [
                'current' => $revenue_net,
                'growth' => round($growth_percent * 100, 1),
                'orders' => $orders,
                'ticket' => round($avg_ticket),
                'top_item' => $topItem['product_name'] ?? '-',
                'top_revenue' => (float)($topItem['revenue'] ?? 0),
                'today_net' => (float)$today_stats['revenue_today'],
                'today_count' => (int)$today_stats['orders_today']
            ],
            'compras' => [
                'total' => $total_cost,
                'count' => (int)$purchasesData['num_compras'],
                'items_criticos' => count($critical_items),
                'top_provider' => $topProvider['proveedor'] ?? '-'
            ],
            'margen' => [
                'bruto' => $revenue_net - $total_cost,
                'percent' => round($margin_percent * 100, 1)
            ],
            'inventario' => [
                'value' => $invValue,
                'count' => (int)$invData['count'],
                'stagnant' => $stagnantData['name'] ?? '-',
                'stagnant_value' => (float)$stagnantData['stock_value'],
                'rotation' => $total_cost > 0 ? round($invValue / $total_cost, 2) : 0,
                'items' => $critical_items
            ],
            'plan_compras' => [
                'next_refresh' => 24,
                'est_cost' => 191664,
                'urgent' => 2,
                'days' => 3
            ],
            'breakeven' => [
                'needed' => round($missingToBreakeven),
                'salaries' => $MONTHLY_SALARIES,
                'margin' => round($margin_percent * 100, 1),
                'progress' => round($monthly_completion * 100, 1),
                'liquidity' => $liquidity
            ],
            'goals' => [
                'daily_actual' => round($daily_average),
                'daily_target' => round($daily_goal),
                'monthly_total' => $revenue_net,
                'monthly_target' => round($safe_target),
                'ritmo' => round($daily_goal)
            ],
            'payment_methods' => $paymentMethods,
            'addresses' => $addresses
        ]
    ]);

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}