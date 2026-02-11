<?php
// Debug API TUU error 400
header('Content-Type: application/json');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
$config = require_once $configPath;

$url = 'https://integrations.payment.haulmer.com/Report/get-report';

// Probar con hoy solamente
$data = [
    'Filters' => [
        'StartDate' => date('Y-m-d'),
        'EndDate' => date('Y-m-d')
    ],
    'page' => 1,
    'pageSize' => 10
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ],
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'request_url' => $url,
    'request_data' => $data,
    'http_code' => $httpCode,
    'curl_error' => $error,
    'response' => $response ? json_decode($response, true) : null,
    'raw_response' => $response,
    'api_key_prefix' => substr($config['tuu_api_key'], 0, 20) . '...'
]);
?>