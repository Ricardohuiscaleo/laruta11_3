<?php
set_time_limit(60);
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
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

try {
    // Usar timezone de Chile para cálculos correctos
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    
    // Determinar día del turno actual (si es 00:00-03:59, aún es el turno del día anterior)
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    
    $currentYear = $shiftToday->format('Y');
    $currentMonth = $shiftToday->format('m');
    $currentDay = (int)$shiftToday->format('d');
    
    // Si estamos antes de las 04:00, el turno actual aún no ha comenzado
    $shiftHasStarted = $currentHour >= 4;
    
    // Obtener ventas desde inicio del mes actual directamente de BD
    $startDate = "$currentYear-$currentMonth-01";
    $endOfMonth = new DateTime("$currentYear-$currentMonth-01");
    $endOfMonth->modify('last day of this month');
    $daysInMonth = (int)$endOfMonth->format('d');
    
    // Obtener transacciones directamente de la BD
    $sql = "SELECT 
                o.installment_amount as amount,
                o.delivery_fee,
                o.created_at,
                o.payment_status
            FROM tuu_orders o
            WHERE DATE(o.created_at) >= ?
            AND DATE(o.created_at) <= ?
            AND o.payment_status = 'paid'
            ORDER BY o.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $now->format('Y-m-d')]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar ventas por DÍA CALENDARIO (sin lógica de turnos)
    $salesByWeekday = array_fill(0, 7, []);
    $salesCurrentMonth = [];
    $deliveryByDay = [];
    $salesByDay = [];
    
    foreach ($transactions as $transaction) {
        $amount = (float)($transaction['amount'] ?? 0);
        $deliveryFee = (float)($transaction['delivery_fee'] ?? 0);
        
        // Usar DATE() directamente de created_at
        $dateStr = substr($transaction['created_at'], 0, 10); // YYYY-MM-DD
        $date = new DateTime($dateStr);
        $weekday = (int)$date->format('w');
        $day = (int)$date->format('d');
        
        // Acumular por día para promedios
        if (!isset($salesByDay[$dateStr])) {
            $salesByDay[$dateStr] = ['total' => 0, 'weekday' => $weekday];
        }
        $salesByDay[$dateStr]['total'] += ($amount - $deliveryFee);
        
        // Acumular ventas del mes actual
        if (!isset($salesCurrentMonth[$day])) {
            $salesCurrentMonth[$day] = 0;
            $deliveryByDay[$day] = 0;
        }
        $salesCurrentMonth[$day] += $amount;
        $deliveryByDay[$day] += $deliveryFee;
    }
    
    // Agrupar totales diarios por día de semana (excluyendo día actual incompleto)
    $todayKey = $now->format('Y-m-d');
    foreach ($salesByDay as $dayKey => $data) {
        if ($dayKey !== $todayKey) {
            $salesByWeekday[$data['weekday']][] = $data['total'];
        }
    }
    
    // Calcular promedio por día de la semana
    $avgByWeekday = [];
    $minSamplesNeeded = 2; // Mínimo 2 días completos por día de semana
    
    foreach ($salesByWeekday as $weekday => $sales) {
        if (count($sales) >= $minSamplesNeeded) {
            // Excluir el valor más bajo si hay suficientes muestras (outliers)
            if (count($sales) > 3) {
                sort($sales);
                array_shift($sales); // Remover el más bajo
            }
            $avgByWeekday[$weekday] = array_sum($sales) / count($sales);
        } else {
            $avgByWeekday[$weekday] = 0;
        }
    }
    
    // Si no hay suficientes datos históricos, usar promedio del mes actual
    $validAverages = array_filter($avgByWeekday);
    if (count($validAverages) < 4 && count($salesCurrentMonth) > 0) {
        // Usar promedio del mes actual como fallback
        $totalAvg = array_sum($salesCurrentMonth) / count($salesCurrentMonth);
        $avgByWeekday = array_fill(0, 7, $totalAvg);
    }
    
    // Generar proyección día por día DESDE EL DÍA 1
    $projection = [];
    $totalReal = 0;
    $totalProjected = 0;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = new DateTime("$currentYear-$currentMonth-$day");
        $weekday = (int)$date->format('w');
        $dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$weekday];
        
        if ($day < $currentDay) {
            // Día pasado completo - usar datos reales SIN delivery
            $amount = $salesCurrentMonth[$day] ?? 0;
            $delivery = $deliveryByDay[$day] ?? 0;
            $netAmount = $amount - $delivery;
            $totalReal += $netAmount;
            $projection[] = [
                'day' => $day,
                'weekday' => $weekday,
                'dayName' => $dayName,
                'year' => (int)$currentYear,
                'month' => (int)$currentMonth,
                'real' => $netAmount,
                'realWithDelivery' => $amount,
                'delivery' => $delivery,
                'projected' => null,
                'isPast' => true
            ];
        } else if ($day == $currentDay && $shiftHasStarted) {
            // Día actual Y turno ya comenzó - usar datos reales SIN delivery
            $amount = $salesCurrentMonth[$day] ?? 0;
            $delivery = $deliveryByDay[$day] ?? 0;
            $netAmount = $amount - $delivery;
            $totalReal += $netAmount;
            $projection[] = [
                'day' => $day,
                'weekday' => $weekday,
                'dayName' => $dayName,
                'year' => (int)$currentYear,
                'month' => (int)$currentMonth,
                'real' => $netAmount,
                'realWithDelivery' => $amount,
                'delivery' => $delivery,
                'projected' => null,
                'isPast' => true,
                'isToday' => true
            ];
        } else {
            // Día futuro - usar promedio del día de la semana
            $projectedAmount = $avgByWeekday[$weekday] ?? 0;
            $totalProjected += $projectedAmount;
            $projection[] = [
                'day' => $day,
                'weekday' => $weekday,
                'dayName' => $dayName,
                'year' => (int)$currentYear,
                'month' => (int)$currentMonth,
                'real' => null,
                'projected' => $projectedAmount,
                'isPast' => false
            ];
        }
    }
    
    $totalMonthProjection = $totalReal + $totalProjected;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'projection' => $projection,
            'totalReal' => $totalReal,
            'totalProjected' => $totalProjected,
            'totalMonthProjection' => $totalMonthProjection,
            'currentDay' => $currentDay,
            'daysInMonth' => $daysInMonth,
            'avgByWeekday' => $avgByWeekday,
            'weekdayNames' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'currentMonth' => (int)$currentMonth,
            'currentYear' => (int)$currentYear
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
