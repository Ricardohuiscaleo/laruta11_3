<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Horarios de atención en Chile
$BUSINESS_HOURS = [
    1 => ['open' => '18:00', 'close' => '00:30', 'name' => 'Lunes'],
    2 => ['open' => '18:00', 'close' => '00:30', 'name' => 'Martes'],
    3 => ['open' => '18:00', 'close' => '00:30', 'name' => 'Miércoles'],
    4 => ['open' => '18:00', 'close' => '00:30', 'name' => 'Jueves'],
    5 => ['open' => '18:00', 'close' => '02:30', 'name' => 'Viernes'],
    6 => ['open' => '18:00', 'close' => '02:30', 'name' => 'Sábado'],
    0 => ['open' => '18:00', 'close' => '00:00', 'name' => 'Domingo']
];

function isWithinBusinessHours() {
    global $BUSINESS_HOURS;
    
    date_default_timezone_set('America/Santiago');
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = (int)$now->format('w');
    $currentTime = $now->format('H:i');
    
    if (!isset($BUSINESS_HOURS[$day])) {
        return false;
    }
    
    $schedule = $BUSINESS_HOURS[$day];
    $openTime = $schedule['open'];
    $closeTime = $schedule['close'];
    
    // Convertir a minutos para comparación
    list($openH, $openM) = explode(':', $openTime);
    list($closeH, $closeM) = explode(':', $closeTime);
    list($currentH, $currentM) = explode(':', $currentTime);
    
    $openMinutes = (int)$openH * 60 + (int)$openM;
    $closeMinutes = (int)$closeH * 60 + (int)$closeM;
    $currentMinutes = (int)$currentH * 60 + (int)$currentM;
    
    // Si cierra después de medianoche
    if ($closeH < $openH) {
        $closeMinutes += 24 * 60;
        if ($currentH < $openH) {
            $currentMinutes += 24 * 60;
        }
    }
    
    return $currentMinutes >= $openMinutes && $currentMinutes <= $closeMinutes;
}

function getBusinessStatus() {
    global $BUSINESS_HOURS;
    
    date_default_timezone_set('America/Santiago');
    $now = new DateTime('now', new DateTimeZone('America/Santiago'));
    $day = (int)$now->format('w');
    
    $isOpen = isWithinBusinessHours();
    $schedule = $BUSINESS_HOURS[$day] ?? null;
    
    if (!$schedule) {
        return [
            'isOpen' => false,
            'currentDay' => 'Desconocido',
            'openTime' => '',
            'closeTime' => '',
            'message' => 'Horario no disponible'
        ];
    }
    
    return [
        'isOpen' => $isOpen,
        'currentDay' => $schedule['name'],
        'openTime' => $schedule['open'],
        'closeTime' => $schedule['close'],
        'message' => $isOpen 
            ? "Abierto hasta las {$schedule['close']}" 
            : "Cerrado - Abre {$schedule['name']} a las {$schedule['open']}",
        'currentTime' => $now->format('H:i'),
        'timezone' => 'America/Santiago'
    ];
}

try {
    $status = getBusinessStatus();
    
    echo json_encode([
        'success' => true,
        'status' => $status
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
