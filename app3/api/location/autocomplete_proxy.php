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
$city = $data['city'] ?? 'Arica';

if (empty($input)) {
    echo json_encode(['error' => 'Input requerido']);
    exit;
}

$apiKey = $config['ruta11_google_maps_api_key'] ?? 'AIzaSyAcK15oZ84Puu5Nc4wDQT_Wyht0xqkbO-A';

// Places API (New) - Autocomplete
$url = "https://places.googleapis.com/v1/places:autocomplete";

$postData = json_encode([
    'input' => $input,
    'includedRegionCodes' => [strtoupper($country)],
    'languageCode' => 'es',
    'locationBias' => [
        'circle' => [
            'center' => [
                'latitude' => -18.4746,
                'longitude' => -70.2979
            ],
            'radius' => 50000.0
        ]
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Goog-Api-Key: ' . $apiKey
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    echo json_encode(['predictions' => [], 'error' => 'API error: ' . $httpCode]);
    exit;
}

// Transformar respuesta al formato esperado
$data = json_decode($response, true);
$predictions = [];

if (isset($data['suggestions'])) {
    foreach ($data['suggestions'] as $suggestion) {
        if (isset($suggestion['placePrediction'])) {
            $pred = $suggestion['placePrediction'];
            $predictions[] = [
                'description' => $pred['text']['text'] ?? '',
                'place_id' => $pred['placeId'] ?? ''
            ];
        }
    }
}

echo json_encode(['predictions' => $predictions]);
?>
