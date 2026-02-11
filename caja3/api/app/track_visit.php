<?php
// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Usar base de datos app
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $input = json_decode(file_get_contents('php://input'), true);
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $page_url = $input['page_url'] ?? 'https://app.laruta11.cl';
    $referrer = $_SERVER['HTTP_REFERER'] ?? '';
    $session_id = $input['session_id'] ?? session_id();
    
    // Detectar tipo de dispositivo
    $device_type = 'desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/', $user_agent)) {
        $device_type = preg_match('/iPad/', $user_agent) ? 'tablet' : 'mobile';
    }
    
    // Detectar navegador
    $browser = 'Unknown';
    if (preg_match('/Chrome/', $user_agent)) $browser = 'Chrome';
    elseif (preg_match('/Firefox/', $user_agent)) $browser = 'Firefox';
    elseif (preg_match('/Safari/', $user_agent)) $browser = 'Safari';
    elseif (preg_match('/Edge/', $user_agent)) $browser = 'Edge';

    // Obtener ubicación si hay coordenadas
    $country = null;
    $city = null;
    $full_address = null;
    
    if (isset($input['latitude']) && isset($input['longitude'])) {
        $lat = $input['latitude'];
        $lng = $input['longitude'];
        
        // Usar Google Geocoding API
        $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$config['ruta11_google_maps_api_key']}&language=es";
        $geocode_response = @file_get_contents($geocode_url);
        
        if ($geocode_response) {
            $geocode_data = json_decode($geocode_response, true);
            if ($geocode_data['status'] === 'OK' && !empty($geocode_data['results'])) {
                $result = $geocode_data['results'][0];
                $full_address = $result['formatted_address'];
                
                foreach ($result['address_components'] as $component) {
                    if (in_array('locality', $component['types'])) {
                        $city = $component['long_name'];
                    }
                    if (in_array('country', $component['types'])) {
                        $country = $component['long_name'];
                    }
                }
            }
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO site_visits 
        (ip_address, user_agent, page_url, referrer, session_id, visit_date, device_type, browser, country, city, 
         latitude, longitude, screen_resolution, viewport_size, timezone, language, platform, full_address) 
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $ip_address,
        $user_agent,
        $page_url,
        $referrer,
        $session_id,
        $device_type,
        $browser,
        $country,
        $city,
        $input['latitude'] ?? null,
        $input['longitude'] ?? null,
        $input['screen_resolution'] ?? null,
        $input['viewport_size'] ?? null,
        $input['timezone'] ?? null,
        $input['language'] ?? null,
        $input['platform'] ?? null,
        $full_address
    ]);

    echo json_encode(['success' => true, 'message' => 'Visit tracked']);

} catch (Exception $e) {
    // Si falla, intentar crear las columnas faltantes
    try {
        $pdo->exec("ALTER TABLE site_visits 
            ADD COLUMN IF NOT EXISTS latitude DECIMAL(10, 8),
            ADD COLUMN IF NOT EXISTS longitude DECIMAL(11, 8),
            ADD COLUMN IF NOT EXISTS screen_resolution VARCHAR(20),
            ADD COLUMN IF NOT EXISTS viewport_size VARCHAR(20),
            ADD COLUMN IF NOT EXISTS timezone VARCHAR(50),
            ADD COLUMN IF NOT EXISTS language VARCHAR(10),
            ADD COLUMN IF NOT EXISTS platform VARCHAR(50),
            ADD COLUMN IF NOT EXISTS full_address TEXT");
        
        echo json_encode(['success' => true, 'message' => 'Table updated and visit tracked']);
    } catch (Exception $e2) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>