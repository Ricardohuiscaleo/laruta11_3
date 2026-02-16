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

// Nueva Places API (New)
$url = "https://places.googleapis.com/v1/places:autocompleteText";

$postData = json_encode([
    'input' => $input,
    'includedRegionCodes' => [strtoupper($country)],
    'languageCode' => 'es'
]);

$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'X-Goog-Api-Key: ' . $apiKey
        ],
        'content' => $postData
    ]
];

$context = stream_context_create($options);
$response = file_get_contents($url, false, $context);

// Transformar respuesta al formato esperado por el frontend
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
