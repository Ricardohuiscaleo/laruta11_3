<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
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

    // Obtener food truck principal (ID 4)
    $stmt = $pdo->prepare("SELECT * FROM food_trucks WHERE id = 4 LIMIT 1");
    $stmt->execute();
    $truck = $stmt->fetch(PDO::FETCH_ASSOC);

    // Calcular si está abierto
    date_default_timezone_set('America/Santiago');
    $now = new DateTime();
    $currentTime = $now->format('H:i:s');
    $dayOfWeek = (int)$now->format('w'); // 0=domingo, 6=sábado

    $isOpen = false;
    $todaySchedule = null;
    $nextOpenTime = null;

    if ($truck) {
        $openTime = $truck['horario_inicio'];
        $closeTime = $truck['horario_fin'];
        
        // Verificar si está abierto
        if ($truck['activo']) {
            if ($closeTime < $openTime) {
                // Horario cruza medianoche (ej: 18:00 - 03:00)
                $isOpen = ($currentTime >= $openTime || $currentTime <= $closeTime);
            } else {
                $isOpen = ($currentTime >= $openTime && $currentTime <= $closeTime);
            }
        }

        $todaySchedule = [
            'horario_inicio' => $openTime,
            'horario_fin' => $closeTime
        ];

        // Calcular próxima apertura
        if (!$isOpen && $truck['activo']) {
            if ($currentTime < $openTime) {
                $nextOpenTime = substr($openTime, 0, 5); // HH:MM
            }
        }
    }

    // Generar horarios de la semana desde la base de datos
    $schedules = [];
    $days = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $dayColumns = ['horario_domingo', 'horario_lunes', 'horario_martes', 'horario_miercoles', 'horario_jueves', 'horario_viernes', 'horario_sabado'];
    
    for ($i = 0; $i < 7; $i++) {
        $dayColumn = $dayColumns[$i];
        $daySchedule = $truck && isset($truck[$dayColumn]) ? $truck[$dayColumn] : null;
        
        if ($daySchedule && strpos($daySchedule, '-') !== false) {
            // Formato: "18:00-01:00"
            list($start, $end) = explode('-', $daySchedule);
            $schedules[] = [
                'day' => $days[$i],
                'start' => trim($start),
                'end' => trim($end),
                'is_today' => $i === $dayOfWeek
            ];
        } else {
            // Fallback al horario general
            $schedules[] = [
                'day' => $days[$i],
                'start' => $truck ? substr($truck['horario_inicio'], 0, 5) : '18:00',
                'end' => $truck ? substr($truck['horario_fin'], 0, 5) : '00:30',
                'is_today' => $i === $dayOfWeek
            ];
        }
    }

    // Obtener todos los trucks activos
    $stmt = $pdo->prepare("SELECT * FROM food_trucks WHERE activo = 1 ORDER BY nombre");
    $stmt->execute();
    $trucks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'status' => [
            'is_open' => $isOpen,
            'current_time' => $now->format('H:i'),
            'today_schedule' => $todaySchedule,
            'status' => $isOpen ? 'open' : ($nextOpenTime ? 'opens_today' : 'closed'),
            'next_open_time' => $nextOpenTime
        ],
        'schedules' => $schedules,
        'trucks' => $trucks
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
