<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$user_lat = floatval($_POST['user_lat']);
$user_lng = floatval($_POST['user_lng']);
$truck_lat = floatval($_POST['truck_lat']);
$truck_lng = floatval($_POST['truck_lng']);

if (!$user_lat || !$user_lng || !$truck_lat || !$truck_lng) {
    echo json_encode(['error' => 'Coordenadas inválidas']);
    exit();
}

// Usar Google Directions API para tiempo real
$url = "https://maps.googleapis.com/maps/api/directions/json?origin={$user_lat},{$user_lng}&destination={$truck_lat},{$truck_lng}&key={$config['google_maps_api_key']}&mode=driving";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['status'] === 'OK' && !empty($data['routes'])) {
    $route = $data['routes'][0];
    $leg = $route['legs'][0];
    
    $distance_km = round($leg['distance']['value'] / 1000, 1);
    $duration_min = round($leg['duration']['value'] / 60);
    
    // Agregar tiempo de preparación (10-15 min)
    $prep_time = rand(10, 15);
    $total_time = $duration_min + $prep_time;
    
    echo json_encode([
        'success' => true,
        'distance_km' => $distance_km,
        'travel_time_min' => $duration_min,
        'prep_time_min' => $prep_time,
        'total_delivery_time' => $total_time,
        'formatted_time' => $total_time . ' min',
        'distance_text' => $leg['distance']['text'],
        'duration_text' => $leg['duration']['text']
    ]);
} else {
    // Fallback: cálculo aproximado
    $earth_radius = 6371;
    $lat_diff = deg2rad($user_lat - $truck_lat);
    $lng_diff = deg2rad($user_lng - $truck_lng);
    
    $a = sin($lat_diff/2) * sin($lat_diff/2) + 
         cos(deg2rad($truck_lat)) * cos(deg2rad($user_lat)) * 
         sin($lng_diff/2) * sin($lng_diff/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    // Tiempo aproximado: 30 km/h en ciudad + preparación
    $travel_time = round(($distance / 30) * 60);
    $prep_time = 15;
    $total_time = $travel_time + $prep_time;
    
    echo json_encode([
        'success' => true,
        'distance_km' => round($distance, 1),
        'travel_time_min' => $travel_time,
        'prep_time_min' => $prep_time,
        'total_delivery_time' => $total_time,
        'formatted_time' => $total_time . ' min',
        'fallback' => true
    ]);
}
?>