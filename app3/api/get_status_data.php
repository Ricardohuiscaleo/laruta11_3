<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
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

    // 1. Obtener horarios
    $stmt = $pdo->prepare("
        SELECT day_of_week, horario_inicio, horario_fin, activo 
        FROM food_truck_schedules 
        WHERE food_truck_id = 4
        ORDER BY day_of_week
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $dayNames = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 0 => 'Domingo'
    ];
    
    // Chile timezone (UTC-3)
    date_default_timezone_set('America/Santiago');
    $today = (int)date('w');
    $currentTime = date('H:i');
    
    // Calcular status con estados intermedios
    $isOpen = false;
    $status = 'closed';
    $nextOpenTime = null;
    $todaySchedule = null;
    
    foreach ($schedules as $schedule) {
        if ($schedule['day_of_week'] == $today && $schedule['activo'] == 1) {
            $todaySchedule = $schedule;
            $start = substr($schedule['horario_inicio'], 0, 5);
            $end = substr($schedule['horario_fin'], 0, 5);
            
            if ($end < $start) {
                $isOpen = $currentTime >= $start || $currentTime <= $end;
            } else {
                $isOpen = $currentTime >= $start && $currentTime <= $end;
            }
            
            // Si está cerrado pero es hoy, calcular cuándo abre
            if (!$isOpen && $currentTime < $start) {
                $status = 'opens_today';
                $nextOpenTime = $start;
            }
            break;
        }
    }
    
    // Si no está abierto hoy, verificar horario de ayer que cruza medianoche
    if (!$isOpen) {
        $yesterday = $today === 0 ? 6 : $today - 1;
        foreach ($schedules as $schedule) {
            if ($schedule['day_of_week'] == $yesterday && $schedule['activo'] == 1) {
                $start = substr($schedule['horario_inicio'], 0, 5);
                $end = substr($schedule['horario_fin'], 0, 5);
                
                if ($end < $start && $currentTime <= $end) {
                    $isOpen = true;
                }
                break;
            }
        }
    }
    
    $formattedSchedules = [];
    $dayOrder = [1, 2, 3, 4, 5, 6, 0]; // Lunes a Domingo
    
    foreach ($dayOrder as $dayNum) {
        foreach ($schedules as $schedule) {
            if ($schedule['day_of_week'] == $dayNum) {
                $isToday = $schedule['day_of_week'] == $today;
                $formattedSchedules[] = [
                    'day' => $dayNames[$schedule['day_of_week']],
                    'day_of_week' => $schedule['day_of_week'],
                    'start' => substr($schedule['horario_inicio'], 0, 5),
                    'end' => $schedule['horario_fin'] === '00:00:00' ? '24:00' : substr($schedule['horario_fin'], 0, 5),
                    'active' => $schedule['activo'],
                    'is_today' => $isToday
                ];
                break;
            }
        }
    }
    
    // 2. Obtener food truck principal
    $stmt = $pdo->prepare("
        SELECT id, nombre, descripcion, latitud, longitud, direccion, tarifa_delivery, activo
        FROM food_trucks 
        WHERE id = 4
    ");
    $stmt->execute();
    $truck = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $trucks = $truck ? [$truck] : [];
    
    echo json_encode([
        'success' => true,
        'schedules' => $formattedSchedules,
        'status' => [
            'is_open' => $isOpen,
            'status' => $isOpen ? 'open' : $status,
            'next_open_time' => $nextOpenTime,
            'current_time' => $currentTime,
            'today_schedule' => $todaySchedule
        ],
        'trucks' => $trucks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>