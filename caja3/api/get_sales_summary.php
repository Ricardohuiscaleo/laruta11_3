<?php
set_time_limit(60); // 60 segundos timeout
ini_set('max_execution_time', 60);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    // Verificar si se solicita el mes completo
    $isMonthView = isset($_GET['month']) && (isset($_GET['year']) || $_GET['month'] === 'current');
    
    if ($isMonthView) {
        if (isset($_GET['year']) && is_numeric($_GET['month'])) {
            // Mes y año específicos
            $year = (int)$_GET['year'];
            $month = (int)$_GET['month'];
            $firstDay = new DateTime("$year-$month-01", new DateTimeZone('America/Santiago'));
            $lastDay = new DateTime($firstDay->format('Y-m-t'), new DateTimeZone('America/Santiago'));
            
            $start_date_chile = $firstDay->format('Y-m-d') . ' 17:30:00';
            $end_date_chile = $lastDay->format('Y-m-d') . ' 23:59:59';
        } else {
            // Mes actual hasta hoy
            $now = new DateTime('now', new DateTimeZone('America/Santiago'));
            $firstDay = new DateTime($now->format('Y-m-01'), new DateTimeZone('America/Santiago'));
            
            $start_date_chile = $firstDay->format('Y-m-d') . ' 17:30:00';
            $end_date_chile = $now->format('Y-m-d H:i:s');
        }
        
        // Convertir a UTC
        $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
    } else {
        // Permitir consultar turnos anteriores con parámetro ?days_ago=1
        $daysAgo = isset($_GET['days_ago']) ? (int)$_GET['days_ago'] : 0;
        
        // Determinar turno automáticamente según día y hora actual EN CHILE
        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
        $currentHourChile = (int)$now->format('G');
        
        // LÓGICA CLAVE: Si son entre 00:00 y 04:00 AM, aún estamos en el turno del día ANTERIOR
        // porque el turno termina a las 04:00 AM del día siguiente
        $shiftStartDate = $now->format('Y-m-d');
        
        if ($currentHourChile >= 0 && $currentHourChile < 4) {
            // Estamos en madrugada (00:00-03:59), el turno es del día anterior
            $shiftStartDate = date('Y-m-d', strtotime($shiftStartDate . ' -1 day'));
        }
        
        // Ajustar por días atrás si se especifica
        if ($daysAgo > 0) {
            $shiftStartDate = date('Y-m-d', strtotime($shiftStartDate . ' -' . $daysAgo . ' days'));
        }
        
        // Turno: desde 17:30 Chile hasta 04:00 del día siguiente (todos los días)
        // Convertir hora Chile a UTC para la query (Chile = UTC-3)
        $start_date_chile = $shiftStartDate . ' 17:30:00';
        $end_date_chile = date('Y-m-d', strtotime($shiftStartDate . ' +1 day')) . ' 04:00:00';
        
        // Convertir a UTC para query (sumar 3 horas)
        $start_date = date('Y-m-d H:i:s', strtotime($start_date_chile . ' +3 hours'));
        $end_date = date('Y-m-d H:i:s', strtotime($end_date_chile . ' +3 hours'));
    }
    
    // Consulta optimizada con índice en created_at
    $sql = "SELECT 
                payment_method,
                COUNT(*) as count,
                SUM(installment_amount) as total
            FROM tuu_orders
            WHERE created_at >= ? 
            AND created_at < ?
            AND payment_status = 'paid'
            AND order_number NOT LIKE 'RL6-%'
            GROUP BY payment_method";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Formatear resultados
    $result = [
        'cash' => ['count' => 0, 'total' => 0],
        'card' => ['count' => 0, 'total' => 0],
        'transfer' => ['count' => 0, 'total' => 0],
        'pedidosya' => ['count' => 0, 'total' => 0],
        'webpay' => ['count' => 0, 'total' => 0],
        'rl6_credit' => ['count' => 0, 'total' => 0]
    ];
    
    foreach ($summary as $row) {
        $method = $row['payment_method'];
        if (isset($result[$method])) {
            $result[$method] = [
                'count' => (int)$row['count'],
                'total' => (float)$row['total']
            ];
        }
    }
    
    // Calcular delivery fees y extras (dinero para el rider)
    $deliverySql = "SELECT 
                        COUNT(*) as delivery_count,
                        SUM(delivery_fee) as delivery_total,
                        SUM(COALESCE(delivery_extras, 0)) as extras_total
                    FROM tuu_orders
                    WHERE created_at >= ? 
                    AND created_at < ?
                    AND payment_status = 'paid'
                    AND order_number NOT LIKE 'RL6-%'
                    AND delivery_type = 'delivery'
                    AND delivery_fee > 0";
    
    $deliveryStmt = $pdo->prepare($deliverySql);
    $deliveryStmt->execute([$start_date, $end_date]);
    $deliveryData = $deliveryStmt->fetch(PDO::FETCH_ASSOC);
    
    $delivery_fees = (float)($deliveryData['delivery_total'] ?? 0);
    $delivery_count = (int)($deliveryData['delivery_count'] ?? 0);
    $delivery_extras = (float)($deliveryData['extras_total'] ?? 0);
    
    // Calcular total general
    $total_general = array_sum(array_column($result, 'total'));
    $total_orders = array_sum(array_column($result, 'count'));
    
    if ($isMonthView) {
        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
        $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $month = $months[(int)$now->format('m') - 1];
        $year = $now->format('Y');
        $shift_hours = 'Mes completo';
        $shift_date = ucfirst($month) . ' ' . $year;
    } else {
        $shift_hours = '17:30-04:00';
        
        // Formatear fecha con rango (ej: "25 al 26 de octubre 2025")
        $startDateObj = new DateTime($shiftStartDate);
        $endDateObj = new DateTime($shiftStartDate);
        $endDateObj->modify('+1 day');
        
        $months = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $startDay = $startDateObj->format('d');
        $endDay = $endDateObj->format('d');
        $month = $months[(int)$startDateObj->format('m') - 1];
        $year = $startDateObj->format('Y');
        
        $shift_date = "{$startDay} al {$endDay} de {$month} {$year}";
    }
    
    echo json_encode([
        'success' => true,
        'summary' => $result,
        'total_general' => $total_general,
        'total_orders' => $total_orders,
        'delivery_fees' => $delivery_fees,
        'delivery_count' => $delivery_count,
        'delivery_extras' => $delivery_extras,
        'shift_hours' => $shift_hours,
        'shift_date' => $shift_date,
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ],
        'debug' => [
            'current_hour_chile' => $currentHourChile,
            'shift_start_date' => $shiftStartDate,
            'day_of_week' => date('N', strtotime($shiftStartDate)),
            'start_date_utc' => $start_date,
            'end_date_utc' => $end_date,
            'start_date_chile' => $start_date_chile ?? 'N/A',
            'end_date_chile' => $end_date_chile ?? 'N/A',
            'query_result_count' => count($summary)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Sales Summary Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
