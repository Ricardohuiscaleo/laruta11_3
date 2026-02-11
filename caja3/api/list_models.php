<?php
header('Content-Type: application/json');
require_once '../config.php';

// Verificar que la clave API esté configurada
if (!isset($config['gemini_api_key']) || empty($config['gemini_api_key'])) {
    echo json_encode(['success' => false, 'error' => 'La clave API de Gemini no está configurada']);
    exit;
}

$apiKey = $config['gemini_api_key'];
$url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;

// Realizar la solicitud
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Preparar la respuesta
$result = [
    'success' => false,
    'api_key_configured' => !empty($apiKey),
    'api_key_length' => strlen($apiKey),
    'http_code' => $httpCode
];

if ($error) {
    $result['error'] = "Error de cURL: $error";
} else {
    $responseData = json_decode($response, true);
    
    if ($httpCode != 200) {
        $result['error'] = "Error HTTP: $httpCode";
        $result['response'] = $responseData;
    } elseif (!$responseData) {
        $result['error'] = "Error al decodificar la respuesta JSON";
        $result['raw_response'] = substr($response, 0, 500);
    } else {
        $result['success'] = true;
        $result['models'] = $responseData;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>