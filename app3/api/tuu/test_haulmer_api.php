<?php
header('Content-Type: application/json');

$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php', 
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

$url = 'https://integrations.payment.haulmer.com/Report/get-report';

$data = [
    'Filters' => [
        'StartDate' => '2025-09-01',
        'EndDate' => '2025-09-14'
    ],
    'page' => 1,
    'pageSize' => 20
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $config['tuu_api_key'],
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo json_encode([
    'http_code' => $httpCode,
    'api_key_used' => substr($config['tuu_api_key'], 0, 20) . '...',
    'request_data' => $data,
    'response' => json_decode($response, true)
]);
?>