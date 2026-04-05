<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

$input = json_decode(file_get_contents('php://input'), true);
$address = trim($input['address'] ?? '');

if (!$address) {
    echo json_encode(['success' => false, 'error' => 'Dirección requerida']);
    exit;
}

// 1. Geocodificar dirección → lat/lng
$api_key = $config['ruta11_google_maps_api_key'] ?? $config['google_maps_api_key'] ?? '';
$encoded = urlencode($address);
$geo_url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encoded}&key={$api_key}&language=es&region=cl";
$geo_response = @file_get_contents($geo_url);
$geo_data = json_decode($geo_response, true);

if (!$geo_data || $geo_data['status'] !== 'OK' || empty($geo_data['results'])) {
    echo json_encode(['success' => false, 'error' => 'No se pudo geocodificar la dirección']);
    exit;
}

$dest_lat = $geo_data['results'][0]['geometry']['location']['lat'];
$dest_lng = $geo_data['results'][0]['geometry']['location']['lng'];

// 2. Obtener carro activo desde BD
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $stmt = $pdo->query("SELECT latitud, longitud, tarifa_delivery FROM food_trucks WHERE activo = 1 ORDER BY id ASC LIMIT 1");
    $truck = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error BD: ' . $e->getMessage()]);
    exit;
}

if (!$truck) {
    echo json_encode(['success' => false, 'error' => 'No hay carro activo']);
    exit;
}

$truck_lat = (float)$truck['latitud'];
$truck_lng = (float)$truck['longitud'];
$base_fee  = (int)$truck['tarifa_delivery'];

// 3. Calcular distancia con Google Directions (fallback Haversine)
$distance_km = null;
$duration_min = null;

$dir_url = "https://maps.googleapis.com/maps/api/directions/json?origin={$truck_lat},{$truck_lng}&destination={$dest_lat},{$dest_lng}&key={$api_key}&mode=driving";
$dir_response = @file_get_contents($dir_url);
$dir_data = json_decode($dir_response, true);

if ($dir_data && $dir_data['status'] === 'OK' && !empty($dir_data['routes'])) {
    $leg = $dir_data['routes'][0]['legs'][0];
    $distance_km = round($leg['distance']['value'] / 1000, 1);
    $duration_min = round($leg['duration']['value'] / 60);
} else {
    // Fallback Haversine
    $R = 6371;
    $dLat = deg2rad($dest_lat - $truck_lat);
    $dLng = deg2rad($dest_lng - $truck_lng);
    $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($truck_lat))*cos(deg2rad($dest_lat))*sin($dLng/2)*sin($dLng/2);
    $distance_km = round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
    $duration_min = round(($distance_km / 30) * 60);
}

// 4. Calcular tarifa dinámica
// base: 0-6km, luego +$1.000 cada 2km adicionales
$surcharge = 0;
if ($distance_km > 6) {
    $extra_km = $distance_km - 6;
    $brackets = ceil($extra_km / 2); // cada 2km = +$1.000
    $surcharge = $brackets * 1000;
}
$dynamic_fee = $base_fee + $surcharge;

echo json_encode([
    'success'      => true,
    'distance_km'  => $distance_km,
    'duration_min' => $duration_min,
    'base_fee'     => $base_fee,
    'surcharge'    => $surcharge,
    'delivery_fee' => $dynamic_fee,
    'label'        => "📍 {$distance_km} km · ~{$duration_min} min · $" . number_format($dynamic_fee, 0, ',', '.'),
]);
