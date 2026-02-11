<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php en múltiples niveles
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
    die(json_encode(['success' => false, 'error' => 'Config file not found']));
}

// Crear conexión usando la configuración de app
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'error' => 'Database connection failed']));
}

try {
    // Obtener horarios de food trucks activos
    $sql = "SELECT horario_inicio, horario_fin FROM food_trucks WHERE activo = 1 LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'No hay food trucks activos',
            'slots' => []
        ]);
        exit;
    }
    
    $truck = $result->fetch_assoc();
    $conn->close();
    
    // Configurar zona horaria de Chile
    date_default_timezone_set('America/Santiago');
    
    $now = new DateTime();
    $currentHour = (int)$now->format('H');
    $currentMinute = (int)$now->format('i');
    
    $slots = [];
    $messages = [];
    
    // Horario fijo 19:00 - 01:00
    if ($currentHour < 19 && $currentHour >= 2) {
        $messages[] = "Food truck abre a las 19:00";
    } else if ($currentHour >= 1 && $currentHour < 19) {
        $messages[] = "Food truck cerrado. Abre a las 19:00";
    }
    
    // Generar slots 19:00 - 01:00
    for ($hour = 19; $hour <= 24; $hour++) {
        for ($minute = 0; $minute < 60; $minute += 30) {
            if ($hour == 24 && $minute > 0) break; // Solo hasta 00:00
            
            $displayHour = $hour == 24 ? 0 : $hour;
            $timeStr = sprintf('%02d:%02d', $displayHour, $minute);
            $endHour = $minute === 30 ? $hour + 1 : $hour;
            $endMinute = $minute === 30 ? 0 : 30;
            if ($endHour == 24) $endHour = 0;
            if ($endHour == 25) $endHour = 1;
            $endTimeStr = sprintf('%02d:%02d', $endHour, $endMinute);
            
            $slots[] = [
                'value' => $timeStr,
                'display' => "$timeStr - $endTimeStr"
            ];
        }
    }
    
    // Agregar slot 00:30 - 01:00
    $slots[] = [
        'value' => '00:30',
        'display' => '00:30 - 01:00'
    ];
    
    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'messages' => $messages,
        'truck_hours' => [
            'open' => '19:00:00',
            'close' => '01:00:00'
        ],
        'current_time' => $now->format('H:i:s'),
        'is_open' => ($currentHour >= 19 || $currentHour < 1)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>