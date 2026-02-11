<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

header('Content-Type: application/json');

// Conectar usando config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

try {
    // Capturar IP real (considerando proxies)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    
    // Capturar información completa
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $today = date('Y-m-d');
    
    // Obtener datos de ubicación del POST
    $input = json_decode(file_get_contents('php://input'), true);
    $latitude = $input['latitude'] ?? null;
    $longitude = $input['longitude'] ?? null;
    $accuracy = $input['accuracy'] ?? null;
    
    // Variables para dirección
    $city = null;
    $region = null;
    $country = null;
    $formatted_address = null;
    
    // Si tenemos coordenadas, obtener dirección
    if ($latitude && $longitude) {
        $geocode_url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$latitude},{$longitude}&key={$config['ruta11_google_maps_api_key']}&language=es";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; Ruta11App/1.0)'
            ]
        ]);
        
        $geocode_response = @file_get_contents($geocode_url, false, $context);
        if ($geocode_response) {
            $geocode_data = json_decode($geocode_response, true);
            
            if ($geocode_data['status'] === 'OK' && !empty($geocode_data['results'])) {
                $result = $geocode_data['results'][0];
                $formatted_address = $result['formatted_address'];
                
                foreach ($result['address_components'] as $component) {
                    $types = $component['types'];
                    
                    if (in_array('locality', $types) || in_array('administrative_area_level_2', $types)) {
                        $city = $component['long_name'];
                    }
                    if (in_array('administrative_area_level_1', $types)) {
                        $region = $component['long_name'];
                    }
                    if (in_array('country', $types)) {
                        $country = $component['long_name'];
                    }
                }
            }
        }
    }
    
    // Registrar vista con ubicación completa
    $query = "INSERT INTO qr_views (view_date, ip_address, user_agent, latitude, longitude, location_accuracy, city, region, country, formatted_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sssdddssss", $today, $ip, $user_agent, $latitude, $longitude, $accuracy, $city, $region, $country, $formatted_address);
    mysqli_stmt_execute($stmt);
    
    echo json_encode(['success' => true, 'ip' => $ip]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>