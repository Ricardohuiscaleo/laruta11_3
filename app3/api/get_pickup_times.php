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
    $dayOfWeek = (int)$now->format('w'); // 0=Domingo, 1=Lunes, ..., 6=Sábado
    
    $slots = [];
    $messages = [];
    
    // Determinar hora de cierre según día
    $closeHour = 24; // Por defecto medianoche
    if ($dayOfWeek >= 1 && $dayOfWeek <= 4) { // Lunes a Jueves
        $closeHour = 24.5; // 00:30
    } else if ($dayOfWeek === 5 || $dayOfWeek === 6) { // Viernes y Sábado
        $closeHour = 26.5; // 02:30
    } else { // Domingo
        $closeHour = 24; // 00:00
    }
    
    // Horarios cada media hora
    $allSlots = [];
    if ($dayOfWeek >= 1 && $dayOfWeek <= 4) { // Lunes a Jueves hasta 00:30
        $allSlots = ['19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00','00:30'];
    } else if ($dayOfWeek === 5 || $dayOfWeek === 6) { // Viernes y Sábado hasta 02:30
        $allSlots = ['19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00','00:30','01:00','01:30','02:00','02:30'];
    } else { // Domingo hasta 00:00
        $allSlots = ['19:00','19:30','20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','00:00'];
    }
    
    // Filtrar slots disponibles (hora actual + 30 min)
    $currentMinute = (int)$now->format('i');
    $currentTime = $currentHour * 60 + $currentMinute + 30;
    
    foreach ($allSlots as $slot) {
        list($slotHour, $slotMin) = explode(':', $slot);
        $slotHour = (int)$slotHour;
        $slotMin = (int)$slotMin;
        $slotTime = $slotHour * 60 + $slotMin;
        
        // Si es después de medianoche (00:00-02:30) y estamos después de las 19:00
        if ($slotHour < 3 && $currentHour >= 19) {
            $slots[] = ['value' => $slot, 'display' => $slot];
        } else if ($slotTime >= $currentTime) {
            $slots[] = ['value' => $slot, 'display' => $slot];
        }
    }
    
    echo json_encode([
        'success' => true,
        'slots' => $slots,
        'messages' => $messages,
        'current_time' => $now->format('H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>