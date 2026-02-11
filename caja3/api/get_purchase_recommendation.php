<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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
    
    // 1. Analizar ventas por día de semana (últimos 30 días)
    $salesByDayQuery = "
        SELECT 
            DAYOFWEEK(oi.created_at) as day_of_week,
            DAYNAME(oi.created_at) as day_name,
            COUNT(DISTINCT o.order_number) as order_count,
            SUM(oi.quantity) as total_items
        FROM tuu_order_items oi
        INNER JOIN tuu_orders o ON oi.order_reference = o.order_number
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND o.payment_status = 'paid'
        GROUP BY DAYOFWEEK(oi.created_at), DAYNAME(oi.created_at)
        ORDER BY day_of_week
    ";
    
    $stmt = $pdo->query($salesByDayQuery);
    $salesByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular promedio y encontrar días peak
    $totalOrders = array_sum(array_column($salesByDay, 'order_count'));
    $avgOrdersPerDay = $totalOrders / max(1, count($salesByDay));
    
    $peakDays = [];
    $lowDays = [];
    
    foreach ($salesByDay as $day) {
        $day['percentage'] = ($day['order_count'] / $avgOrdersPerDay) * 100;
        
        if ($day['percentage'] >= 120) {
            $peakDays[] = $day;
        } elseif ($day['percentage'] <= 70) {
            $lowDays[] = $day;
        }
    }
    
    // 2. Obtener datos del Plan de Compras (usa el mismo cálculo que la lista)
    $days = $_GET['days'] ?? 3;
    
    // Llamar al API de plan de compras para obtener ingredientes críticos
    $planUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/get_purchase_plan.php?days=$days";
    $planData = @file_get_contents($planUrl);
    $plan = $planData ? json_decode($planData, true) : null;
    
    $criticalCount = 0;
    $urgentCount = 0;
    $criticalItems = [];
    $urgentItems = [];
    
    if ($plan && $plan['success'] && isset($plan['data']['purchase_list'])) {
        foreach ($plan['data']['purchase_list'] as $item) {
            $stock = floatval($item['current_stock']);
            $needed = floatval($item['needed']);
            
            // Crítico: stock = 0 o < 10% de lo necesario
            if ($stock == 0 || $stock < ($needed * 0.1)) {
                $criticalCount++;
                $criticalItems[] = $item['ingredient_name'];
            }
            // Urgente: stock < 50% de lo necesario
            elseif ($stock < ($needed * 0.5)) {
                $urgentCount++;
                $urgentItems[] = $item['ingredient_name'];
            }
        }
    }
    
    // 3. Calcular días óptimos de compra con fechas exactas
    date_default_timezone_set('America/Santiago');
    $today = date('N'); // 1=Lunes, 7=Domingo
    $todayDate = new DateTime('now', new DateTimeZone('America/Santiago'));
    $recommendations = [];
    
    // Traducción de días
    $dayTranslation = [
        'Monday' => 'Lunes',
        'Tuesday' => 'Martes',
        'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves',
        'Friday' => 'Viernes',
        'Saturday' => 'Sábado',
        'Sunday' => 'Domingo'
    ];
    
    // Lógica de recomendación
    if ($criticalCount > 0) {
        $itemsList = implode(', ', array_slice($criticalItems, 0, 3));
        if (count($criticalItems) > 3) $itemsList .= ' y ' . (count($criticalItems) - 3) . ' más';
        
        $recommendations[] = [
            'priority' => 'CRÍTICO',
            'day' => 'HOY ' . $todayDate->format('d/m'),
            'reason' => "Stock crítico: $itemsList",
            'color' => '#dc2626',
            'action' => 'Comprar inmediatamente',
            'items' => $criticalItems
        ];
    }
    
    if ($urgentCount > 0) {
        $itemsList = implode(', ', array_slice($urgentItems, 0, 3));
        if (count($urgentItems) > 3) $itemsList .= ' y ' . (count($urgentItems) - 3) . ' más';
        
        $tomorrow = clone $todayDate;
        $tomorrow->modify('+1 day');
        
        $recommendations[] = [
            'priority' => 'URGENTE',
            'day' => 'MAÑANA ' . $tomorrow->format('d/m'),
            'reason' => "Stock bajo: $itemsList",
            'color' => '#f59e0b',
            'action' => 'Programar compra para mañana',
            'items' => $urgentItems
        ];
    }
    
    // Recomendar días basados en análisis de ventas con fechas exactas
    $addedDates = []; // Track all added dates globally
    $dayNames = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    
    // Comprar 1 día antes de los días peak
    foreach ($peakDays as $peak) {
        $dayBefore = $peak['day_of_week'] - 1;
        if ($dayBefore < 1) $dayBefore = 7;
        
        $peakDayEs = $dayTranslation[$peak['day_name']] ?? $peak['day_name'];
        
        // Calcular próxima fecha de ese día
        $nextDate = clone $todayDate;
        $daysToAdd = ($dayBefore - $today + 7) % 7;
        if ($daysToAdd == 0) $daysToAdd = 7;
        $nextDate->modify("+$daysToAdd days");
        
        $dateKey = $nextDate->format('Y-m-d');
        if (in_array($dateKey, $addedDates)) continue;
        $addedDates[] = $dateKey;
        
        $recommendations[] = [
            'priority' => 'PLANIFICADO',
            'day' => $dayNames[$dayBefore] . ' ' . $nextDate->format('d/m'),
            'reason' => "Preparar para $peakDayEs (día peak: {$peak['order_count']} pedidos)",
            'color' => '#10b981',
            'action' => 'Compra estratégica antes del peak',
            'peak_day' => $peakDayEs,
            'peak_orders' => $peak['order_count']
        ];
    }
    
    // Recomendar compras en días de baja demanda con fechas exactas
    foreach ($lowDays as $low) {
        $dayName = $dayNames[$low['day_of_week']];
        
        // Calcular próxima fecha de ese día
        $nextDate = clone $todayDate;
        $daysToAdd = ($low['day_of_week'] - $today + 7) % 7;
        if ($daysToAdd == 0) $daysToAdd = 7;
        $nextDate->modify("+$daysToAdd days");
        
        $dateKey = $nextDate->format('Y-m-d');
        if (in_array($dateKey, $addedDates)) continue;
        $addedDates[] = $dateKey;
        
        $recommendations[] = [
            'priority' => 'ÓPTIMO',
            'day' => $dayName . ' ' . $nextDate->format('d/m'),
            'reason' => "Baja demanda ({$low['order_count']} pedidos). Tiempo libre para comprar",
            'color' => '#0284c7',
            'action' => 'Día ideal: menos ocupado',
            'orders' => $low['order_count']
        ];
    }
    
    // 4. Calcular días óptimos sugeridos (frecuencia)
    $avgDailyOrders = $totalOrders / 30;
    $suggestedFrequency = 3; // Por defecto
    
    if ($avgDailyOrders > 20) {
        $suggestedFrequency = 2; // Comprar cada 2 días si hay alta demanda
    } elseif ($avgDailyOrders < 10) {
        $suggestedFrequency = 4; // Comprar cada 4 días si hay baja demanda
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'recommendations' => $recommendations,
            'sales_analysis' => [
                'total_orders_30d' => $totalOrders,
                'avg_orders_per_day' => round($avgDailyOrders, 1),
                'peak_days' => $peakDays,
                'low_days' => $lowDays,
                'sales_by_day' => $salesByDay
            ],
            'stock_analysis' => [
                'critical_ingredients' => $criticalCount,
                'urgent_ingredients' => $urgentCount,
                'total_ingredients' => $plan && $plan['success'] ? count($plan['data']['purchase_list']) : 0
            ],
            'suggested_frequency' => [
                'days' => $suggestedFrequency,
                'reason' => $avgDailyOrders > 20 
                    ? 'Alta demanda requiere compras frecuentes' 
                    : ($avgDailyOrders < 10 
                        ? 'Baja demanda permite compras espaciadas' 
                        : 'Demanda moderada, frecuencia estándar')
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
