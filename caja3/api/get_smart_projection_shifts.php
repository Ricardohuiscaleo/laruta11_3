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
    // Modo de proyección: 'simple' o 'weighted'
    $mode = $_GET['mode'] ?? 'weighted';
    
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    
    // Determinar día del turno actual
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    
    $currentYear = $shiftToday->format('Y');
    $currentMonth = $shiftToday->format('m');
    $currentDay = (int)$shiftToday->format('d');
    
    // Si estamos antes de las 17:00, el turno actual aún no ha comenzado
    $shiftHasStarted = $currentHour >= 17 || $currentHour < 4;
    
    $startDate = "$currentYear-$currentMonth-01";
    $endOfMonth = new DateTime("$currentYear-$currentMonth-01");
    $endOfMonth->modify('last day of this month');
    $daysInMonth = (int)$endOfMonth->format('d');
    
    // Obtener TODO el histórico para calcular promedios por día de semana
    $sql = "SELECT 
                o.installment_amount as amount,
                o.delivery_fee,
                o.created_at,
                o.payment_status
            FROM tuu_orders o
            WHERE o.payment_status = 'paid'
            AND o.order_number NOT LIKE 'RL6-%'
            ORDER BY o.created_at ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Agrupar TODO el histórico por día de semana
    $salesByWeekday = array_fill(0, 7, []);
    $salesByShiftDay = []; // Solo para el mes actual
    $todayKey = $shiftToday->format('Y-m-d');
    
    foreach ($transactions as $transaction) {
        $amount = (float)($transaction['amount'] ?? 0);
        $deliveryFee = (float)($transaction['delivery_fee'] ?? 0);
        
        // Saltar transacciones inválidas
        if ($amount <= 0) continue;
        
        $netAmount = $amount - $deliveryFee;
        
        // Convertir a hora Chile
        $transDate = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
        $transDate->setTimezone(new DateTimeZone('America/Santiago'));
        $hour = (int)$transDate->format('G');
        
        // Aplicar lógica de turnos: 00:00-03:59 pertenece al día anterior
        $shiftDate = clone $transDate;
        if ($hour >= 0 && $hour < 4) {
            $shiftDate->modify('-1 day');
        }
        
        $weekday = (int)$shiftDate->format('w');
        $dateKey = $shiftDate->format('Y-m-d');
        
        // Acumular por día para el mes actual
        if ($shiftDate->format('Y-m') === "$currentYear-$currentMonth") {
            if (!isset($salesByShiftDay[$dateKey])) {
                $salesByShiftDay[$dateKey] = ['total' => 0, 'weekday' => $weekday, 'day' => (int)$shiftDate->format('d')];
            }
            $salesByShiftDay[$dateKey]['total'] += $netAmount;
        }
        
        // Acumular por día de semana (TODO el histórico, SIEMPRE excluyendo hoy)
        $isToday = ($dateKey === $todayKey);
        
        if (!$isToday) {
            if (!isset($salesByWeekday[$weekday][$dateKey])) {
                $salesByWeekday[$weekday][$dateKey] = 0;
            }
            $salesByWeekday[$weekday][$dateKey] += $netAmount;
        }
    }
    
    // Calcular AMBOS promedios: simple y ponderado
    $avgByWeekdaySimple = [];
    $avgByWeekdayWeighted = [];
    $sampleCountSimple = []; // Contador de días por weekday (histórico completo)
    $sampleCountWeighted = []; // Contador de días por weekday (últimos 15 días)
    
    // MODO SIMPLE: Promedio histórico sin ponderación
    foreach ($salesByWeekday as $weekday => $dayTotals) {
        if (count($dayTotals) > 0) {
            $totals = array_values($dayTotals);
            $avgByWeekdaySimple[$weekday] = array_sum($totals) / count($totals);
            $sampleCountSimple[$weekday] = count($dayTotals);
        } else {
            $avgByWeekdaySimple[$weekday] = 0;
            $sampleCountSimple[$weekday] = 0;
        }
    }
    
    // MODO PONDERADO: 90% últimos 15 días, 10% histórico antiguo (más reactivo a tendencias)
    foreach ($salesByWeekday as $weekday => $dayTotals) {
        if (count($dayTotals) > 0) {
            ksort($dayTotals);
            $dates = array_keys($dayTotals);
            $totals = array_values($dayTotals);
            
            $recent = [];
            $old = [];
            $cutoffDateObj = clone $shiftToday;
            $cutoffDateObj->modify('-15 days'); // Cambio: 15 días en vez de 30
            $cutoffDate = $cutoffDateObj->format('Y-m-d');
            
            foreach ($dates as $i => $date) {
                if ($date >= $cutoffDate) {
                    $recent[] = $totals[$i];
                } else {
                    $old[] = $totals[$i];
                }
            }
            
            $recentAvg = count($recent) > 0 ? array_sum($recent) / count($recent) : 0;
            $oldAvg = count($old) > 0 ? array_sum($old) / count($old) : 0;
            
            if ($recentAvg > 0 && $oldAvg > 0) {
                $avgByWeekdayWeighted[$weekday] = ($recentAvg * 0.9) + ($oldAvg * 0.1); // Cambio: 90/10 en vez de 70/30
            } elseif ($recentAvg > 0) {
                $avgByWeekdayWeighted[$weekday] = $recentAvg;
            } elseif ($oldAvg > 0) {
                $avgByWeekdayWeighted[$weekday] = $oldAvg;
            } else {
                $avgByWeekdayWeighted[$weekday] = 0;
            }
            $sampleCountWeighted[$weekday] = count($recent); // Solo contar días recientes (últimos 15)
        } else {
            $avgByWeekdayWeighted[$weekday] = 0;
            $sampleCountWeighted[$weekday] = 0;
        }
    }
    
    // Calcular promedio general como fallback
    $totalAvg = 0;
    $allDayTotals = [];
    foreach ($salesByWeekday as $dayTotals) {
        $allDayTotals = array_merge($allDayTotals, array_values($dayTotals));
    }
    if (count($allDayTotals) > 0) {
        $totalAvg = array_sum($allDayTotals) / count($allDayTotals);
    }
    
    // Usar promedio general para días sin datos en ambos modos
    foreach ($avgByWeekdaySimple as $weekday => $avg) {
        if ($avg == 0 && $totalAvg > 0) {
            $avgByWeekdaySimple[$weekday] = $totalAvg;
        }
    }
    foreach ($avgByWeekdayWeighted as $weekday => $avg) {
        if ($avg == 0 && $totalAvg > 0) {
            $avgByWeekdayWeighted[$weekday] = $totalAvg;
        }
    }
    
    // Generar proyección día por día con AMBOS cálculos
    $projection = [];
    $totalReal = 0;
    $totalProjectedSimple = 0;
    $totalProjectedWeighted = 0;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = new DateTime("$currentYear-$currentMonth-$day");
        $weekday = (int)$date->format('w');
        $dayName = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'][$weekday];
        $dateKey = $date->format('Y-m-d');
        
        if ($day < $currentDay) {
            // Día pasado (turno completo)
            $dayData = $salesByShiftDay[$dateKey] ?? ['total' => 0];
            $totalReal += $dayData['total'];
            $projection[] = [
                'day' => $day,
                'weekday' => $weekday,
                'dayName' => $dayName,
                'year' => (int)$currentYear,
                'month' => (int)$currentMonth,
                'real' => $dayData['total'],
                'projectedSimple' => $avgByWeekdaySimple[$weekday] ?? 0,
                'projectedWeighted' => $avgByWeekdayWeighted[$weekday] ?? 0,
                'isPast' => true,
                'isToday' => ($day == $currentDay)
            ];
        } else {
            // Día futuro
            $projectedSimple = $avgByWeekdaySimple[$weekday] ?? 0;
            $projectedWeighted = $avgByWeekdayWeighted[$weekday] ?? 0;
            $totalProjectedSimple += $projectedSimple;
            $totalProjectedWeighted += $projectedWeighted;
            $projection[] = [
                'day' => $day,
                'weekday' => $weekday,
                'dayName' => $dayName,
                'year' => (int)$currentYear,
                'month' => (int)$currentMonth,
                'real' => null,
                'projectedSimple' => $projectedSimple,
                'projectedWeighted' => $projectedWeighted,
                'isPast' => false
            ];
        }
    }
    
    $totalMonthProjectionSimple = $totalReal + $totalProjectedSimple;
    $totalMonthProjectionWeighted = $totalReal + $totalProjectedWeighted;
    
    // Calcular totales de muestras
    $totalSampleSimple = array_sum($sampleCountSimple);
    $totalSampleWeighted = array_sum($sampleCountWeighted);
    $daysWithRealData = count($salesByShiftDay); // Días con ventas reales en el mes actual
    
    echo json_encode([
        'success' => true,
        'data' => [
            'projection' => $projection,
            'totalReal' => $totalReal,
            'totalProjectedSimple' => $totalProjectedSimple,
            'totalProjectedWeighted' => $totalProjectedWeighted,
            'totalMonthProjectionSimple' => $totalMonthProjectionSimple,
            'totalMonthProjectionWeighted' => $totalMonthProjectionWeighted,
            'currentDay' => $currentDay,
            'daysInMonth' => $daysInMonth,
            'avgByWeekdaySimple' => $avgByWeekdaySimple,
            'avgByWeekdayWeighted' => $avgByWeekdayWeighted,
            'sampleCountSimple' => $sampleCountSimple,
            'sampleCountWeighted' => $sampleCountWeighted,
            'totalSampleSimple' => $totalSampleSimple,
            'totalSampleWeighted' => $totalSampleWeighted,
            'daysWithRealData' => $daysWithRealData,
            'weekdayNames' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'currentMonth' => (int)$currentMonth,
            'currentYear' => (int)$currentYear,
            'shiftLogic' => true,
            'projectionMode' => $mode,
            'debug' => [
                'currentHour' => $currentHour,
                'shiftHasStarted' => $shiftHasStarted,
                'todayKey' => $todayKey,
                'historical_samples' => array_map('count', $salesByWeekday),
                'totalAvg' => $totalAvg,
                'now_chile' => $now->format('Y-m-d H:i:s'),
                'total_transactions' => count($transactions)
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
