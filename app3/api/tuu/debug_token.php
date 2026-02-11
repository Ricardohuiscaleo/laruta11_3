<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
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
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

$debug = [];

// PASO 1: Obtener Token
$url_base = 'https://core.payment.haulmer.com/api/v1/payment';
$token_url = $url_base . '/token/' . $config['tuu_online_rut'];

$debug['step1'] = [
    'url' => $token_url,
    'rut' => $config['tuu_online_rut'],
    'secret_length' => strlen($config['tuu_online_secret'])
];

$ch = curl_init($token_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $config['tuu_online_secret']
]);

$token_response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$debug['step1']['http_code'] = $httpCode;
$debug['step1']['response'] = $token_response;

if ($httpCode !== 200) {
    $debug['error'] = 'Error en paso 1 - obtener token';
    echo json_encode($debug);
    exit;
}

$token_data = json_decode($token_response, true);
$debug['step1']['token_received'] = isset($token_data['token']);

if (!isset($token_data['token'])) {
    $debug['error'] = 'Token no recibido en respuesta';
    echo json_encode($debug);
    exit;
}

// PASO 2: Validar Token
$validate_url = $url_base . '/validatetoken';
$validate_data = ['token' => $token_data['token']];

$debug['step2'] = [
    'url' => $validate_url,
    'token_length' => strlen($token_data['token'])
];

$ch = curl_init($validate_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validate_data));

$validate_response = curl_exec($ch);
$validate_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$debug['step2']['http_code'] = $validate_httpCode;
$debug['step2']['response'] = $validate_response;

$validate_result = json_decode($validate_response, true);
$debug['step2']['secret_key_received'] = isset($validate_result['secret_key']);
$debug['step2']['account_id_received'] = isset($validate_result['account_id']);

echo json_encode($debug, JSON_PRETTY_PRINT);
?>