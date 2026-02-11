<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

// Test simple con datos mínimos
$paymentData = [
    'idempotencyKey' => 'TEST-' . time(),
    'amount' => 1000,
    'device' => '6010B232541609909',
    'description' => 'Test de pago',
    'dteType' => 48,
    'paymentMethod' => 1
];

$url = 'https://integrations.payment.haulmer.com/RemotePayment/v2/Create';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-API-Key: ' . $config['tuu_api_key'],
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'request_data' => $paymentData,
    'http_code' => $httpCode,
    'curl_error' => $error,
    'raw_response' => $response,
    'parsed_response' => json_decode($response, true)
], JSON_PRETTY_PRINT);
?>