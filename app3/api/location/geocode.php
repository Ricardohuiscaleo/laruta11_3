<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$lat = floatval($_POST['lat']);
$lng = floatval($_POST['lng']);

if (!$lat || !$lng) {
    echo json_encode(['error' => 'Coordenadas inválidas']);
    exit();
}

// Usar Google Geocoding API
$url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$config['ruta11_google_maps_api_key']}&language=es";

$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['status'] === 'OK' && !empty($data['results'])) {
    $result = $data['results'][0];
    
    // Extraer componentes de dirección
    $components = $result['address_components'];
    $address_info = [
        'formatted_address' => $result['formatted_address'],
        'street_number' => '',
        'route' => '',
        'locality' => '',
        'administrative_area_level_2' => '',
        'administrative_area_level_1' => '',
        'country' => '',
        'postal_code' => ''
    ];
    
    foreach ($components as $component) {
        $types = $component['types'];
        
        if (in_array('street_number', $types)) {
            $address_info['street_number'] = $component['long_name'];
        }
        if (in_array('route', $types)) {
            $address_info['route'] = $component['long_name'];
        }
        if (in_array('locality', $types)) {
            $address_info['locality'] = $component['long_name'];
        }
        if (in_array('administrative_area_level_2', $types)) {
            $address_info['administrative_area_level_2'] = $component['long_name'];
        }
        if (in_array('administrative_area_level_1', $types)) {
            $address_info['administrative_area_level_1'] = $component['long_name'];
        }
        if (in_array('country', $types)) {
            $address_info['country'] = $component['long_name'];
        }
        if (in_array('postal_code', $types)) {
            $address_info['postal_code'] = $component['long_name'];
        }
    }
    
    // Construir dirección legible
    $street = trim($address_info['route'] . ' ' . $address_info['street_number']);
    $city = $address_info['locality'];
    $region = $address_info['administrative_area_level_1'];
    $country = $address_info['country'];
    
    echo json_encode([
        'success' => true,
        'formatted_address' => $result['formatted_address'],
        'components' => $address_info,
        'readable' => [
            'street' => $street ?: 'Calle no disponible',
            'city' => $city ?: 'Ciudad no disponible',
            'region' => $region ?: 'Región no disponible',
            'country' => $country ?: 'País no disponible'
        ]
    ]);
} else {
    echo json_encode(['error' => 'No se pudo obtener información de la dirección', 'status' => $data['status'] ?? 'unknown']);
}
?>