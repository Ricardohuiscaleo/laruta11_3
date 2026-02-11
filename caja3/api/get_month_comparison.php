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
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $currentMonth = (int)$now->format('m');
    $currentYear = (int)$now->format('Y');
    $previousMonth = $currentMonth === 1 ? 12 : $currentMonth - 1;
    $previousYear = $currentMonth === 1 ? $currentYear - 1 : $currentYear;
    
    // Obtener datos de ambos meses
    $currentStart = "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . "-01";
    $currentEnd = $now->format('Y-m-d');
    $previousStart = "$previousYear-" . str_pad($previousMonth, 2, '0', STR_PAD_LEFT) . "-01";
    $previousEnd = "$previousYear-" . str_pad($previousMonth, 2, '0', STR_PAD_LEFT) . "-" . date('t', strtotime($previousStart));
    
    // Llamar API TUU para ambos meses
    $currentUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/tuu/get_from_mysql.php?start_date=$currentStart&end_date=$currentEnd";
    $previousUrl = "http://" . $_SERVER['HTTP_HOST'] . "/api/tuu/get_from_mysql.php?start_date=$previousStart&end_date=$previousEnd";
    
    $currentData = @file_get_contents($currentUrl);
    $previousData = @file_get_contents($previousUrl);
    
    if (!$currentData || !$previousData) {
        throw new Exception('No se pudo obtener datos');
    }
    
    $currentResponse = json_decode($currentData, true);
    $previousResponse = json_decode($previousData, true);
    
    if (!$currentResponse['success'] || !$previousResponse['success']) {
        throw new Exception('Error en respuesta de TUU');
    }
    
    $currentTransactions = $currentResponse['data']['all_transactions'] ?? [];
    $previousTransactions = $previousResponse['data']['all_transactions'] ?? [];
    
    // Agrupar por día de la semana
    $currentByWeekday = array_fill(0, 7, 0);
    $previousByWeekday = array_fill(0, 7, 0);
    
    foreach ($currentTransactions as $t) {
        $date = new DateTime($t['created_at'], new DateTimeZone('America/Santiago'));
        $hour = (int)$date->format('G');
        $minute = (int)$date->format('i');
        
        $shiftDate = clone $date;
        // Turno: 17:30 a 04:00 del día siguiente
        if ($hour >= 0 && $hour < 4) {
            $shiftDate->modify('-1 day');
        } elseif ($hour < 17 || ($hour == 17 && $minute < 30)) {
            $shiftDate->modify('-1 day');
        }
        
        // Verificar que el shift day esté en el mes correcto
        if ($shiftDate->format('Y-m') === "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT)) {
            $weekday = (int)$shiftDate->format('w');
            $currentByWeekday[$weekday] += (float)($t['amount'] ?? 0);
        }
    }
    
    foreach ($previousTransactions as $t) {
        $date = new DateTime($t['created_at'], new DateTimeZone('America/Santiago'));
        $hour = (int)$date->format('G');
        $minute = (int)$date->format('i');
        
        $shiftDate = clone $date;
        // Turno: 17:30 a 04:00 del día siguiente
        if ($hour >= 0 && $hour < 4) {
            $shiftDate->modify('-1 day');
        } elseif ($hour < 17 || ($hour == 17 && $minute < 30)) {
            $shiftDate->modify('-1 day');
        }
        
        // Verificar que el shift day esté en el mes correcto
        if ($shiftDate->format('Y-m') === "$previousYear-" . str_pad($previousMonth, 2, '0', STR_PAD_LEFT)) {
            $weekday = (int)$shiftDate->format('w');
            $previousByWeekday[$weekday] += (float)($t['amount'] ?? 0);
        }
    }
    
    // Reordenar para que empiece en Lunes (índice 1) y termine en Domingo (índice 0)
    $currentReordered = [
        $currentByWeekday[1], // Lunes
        $currentByWeekday[2], // Martes
        $currentByWeekday[3], // Miércoles
        $currentByWeekday[4], // Jueves
        $currentByWeekday[5], // Viernes
        $currentByWeekday[6], // Sábado
        $currentByWeekday[0]  // Domingo
    ];
    
    $previousReordered = [
        $previousByWeekday[1], // Lunes
        $previousByWeekday[2], // Martes
        $previousByWeekday[3], // Miércoles
        $previousByWeekday[4], // Jueves
        $previousByWeekday[5], // Viernes
        $previousByWeekday[6], // Sábado
        $previousByWeekday[0]  // Domingo
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'currentMonth' => [
                'month' => $currentMonth,
                'year' => $currentYear,
                'salesByWeekday' => $currentReordered
            ],
            'previousMonth' => [
                'month' => $previousMonth,
                'year' => $previousYear,
                'salesByWeekday' => $previousReordered
            ],
            'weekdayNames' => ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo']
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
