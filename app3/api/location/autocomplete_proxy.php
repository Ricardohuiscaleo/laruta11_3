<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config = require_once __DIR__ . '/../../config.php';
$data = json_decode(file_get_contents('php://input'), true);

$input = $data['input'] ?? '';
$country = $data['country'] ?? 'cl';

if (empty($input)) {
    echo json_encode(['error' => 'Input requerido']);
    exit;
}

$apiKey = $config['ruta11_google_maps_api_key'] ?? 'AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A';

$url = "https://maps.googleapis.com/maps/api/place/autocomplete/json?" . http_build_query([
    'input' => $input,
    'components' => "country:$country",
    'language' => 'es',
    'key' => $apiKey
]);

$response = file_get_contents($url);
echo $response;
?>
