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
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 7;
    
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentHour = (int)$now->format('G');
    
    // Determinar día del turno actual
    $shiftToday = clone $now;
    if ($currentHour >= 0 && $currentHour < 4) {
        $shiftToday->modify('-1 day');
    }
    
    // Calcular fecha de inicio: restar días COMPLETOS desde el día del turno actual
    $startDate = clone $shiftToday;
    $startDate->modify("-" . ($days - 1) . " days"); // -1 porque incluimos el día actual
    
    // Llamar a la API de TUU (usar shiftToday para respetar lógica de turnos)
    $apiUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/tuu/get_from_mysql.php?start_date=" . $startDate->format('Y-m-d') . "&end_date=" . $shiftToday->format('Y-m-d');
    $tuuData = @file_get_contents($apiUrl);
    
    if (!$tuuData) {
        throw new Exception('No se pudo obtener datos de TUU');
    }
    
    $tuuResponse = json_decode($tuuData, true);
    if (!$tuuResponse || !$tuuResponse['success']) {
        throw new Exception('Error en respuesta de TUU');
    }
    
    $transactions = $tuuResponse['data']['all_transactions'] ?? [];
    $salesByShiftDay = [];
    
    foreach ($transactions as $transaction) {
        // Crear fecha en UTC y convertir a Chile
        $date = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('America/Santiago'));
        $amount = (float)($transaction['amount'] ?? 0);
        $deliveryFee = (float)($transaction['delivery_fee'] ?? 0);
        $hour = (int)$date->format('G');
        
        // LÓGICA EXACTA: Si es 00:00-03:59, pertenece al turno del día anterior
        $shiftDate = clone $date;
        if ($hour >= 0 && $hour < 4) {
            $shiftDate->modify('-1 day');
        }
        
        $shiftDayKey = $shiftDate->format('Y-m-d');
        
        if (!isset($salesByShiftDay[$shiftDayKey])) {
            $salesByShiftDay[$shiftDayKey] = [
                'date' => $shiftDate->format('Y-m-d'),
                'weekday' => (int)$shiftDate->format('w'),
                'total' => 0,
                'delivery' => 0
            ];
        }
        
        $salesByShiftDay[$shiftDayKey]['total'] += $amount;
        $salesByShiftDay[$shiftDayKey]['delivery'] += $deliveryFee;
    }
    
    // Generar TODOS los días del rango (incluso sin ventas)
    $allDays = [];
    $currentDate = clone $startDate;
    
    while ($currentDate <= $shiftToday) {
        $dateKey = $currentDate->format('Y-m-d');
        
        if (isset($salesByShiftDay[$dateKey])) {
            $allDays[] = $salesByShiftDay[$dateKey];
        } else {
            // Día sin ventas
            $allDays[] = [
                'date' => $dateKey,
                'weekday' => (int)$currentDate->format('w'),
                'total' => 0,
                'delivery' => 0
            ];
        }
        
        $currentDate->modify('+1 day');
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'salesByDay' => $allDays,
            'days' => $days,
            'actualDays' => count($allDays)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
