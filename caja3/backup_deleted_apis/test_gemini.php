<?php
header('Content-Type: application/json');
require_once '../config.php';

// Verificar que la clave API esté configurada
if (!isset($config['gemini_api_key']) || empty($config['gemini_api_key'])) {
    echo json_encode(['success' => false, 'error' => 'La clave API de Gemini no está configurada']);
    exit;
}

$apiKey = $config['gemini_api_key'];
$url = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

// Registrar el tiempo de inicio para calcular el tiempo de generación
$startTime = microtime(true);

// Datos de prueba simples
$data = [
    "contents" => [
        [
            "parts" => [
                [
                    "text" => "Genera un breve análisis financiero para un negocio de sándwiches en formato HTML."
                ]
            ]
        ]
    ],
    "generationConfig" => [
        "temperature" => 0.7,
        "maxOutputTokens" => 4096,
        "topK" => 64,
        "topP" => 0.95
    ]
];

// Realizar la solicitud
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Aumentar el timeout a 60 segundos para Gemini 2.5

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Calcular el tiempo de generación en segundos
$generationTime = microtime(true) - $startTime;

// Preparar la respuesta
$result = [
    'success' => false,
    'api_key_configured' => !empty($apiKey),
    'api_key_length' => strlen($apiKey),
    'http_code' => $httpCode,
    'generation_time' => round($generationTime, 2) // Tiempo en segundos, redondeado a 2 decimales
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
    } elseif (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        $result['success'] = true;
        $content = $responseData['candidates'][0]['content']['parts'][0]['text'];
        
        // Limpiar bloques de código markdown
        if (preg_match('/```html\s*(.+?)\s*```/s', $content, $matches)) {
            $result['content_raw'] = $content; // Guardar contenido original
            $result['content'] = $matches[1]; // Extraer solo el HTML
        } else {
            $result['content'] = $content;
        }
        
        // Incluir información de tokens si está disponible
        if (isset($responseData['usageMetadata'])) {
            $result['usage_metadata'] = $responseData['usageMetadata'];
            
            // Calcular porcentaje de tokens disponibles (asumiendo un límite de 4096 tokens)
            $tokensUsed = $responseData['usageMetadata']['totalTokenCount'] ?? 0;
            $tokenLimit = 4096; // Límite máximo de tokens para gemini-2.5-flash
            $percentageUsed = ($tokensUsed / $tokenLimit) * 100;
            $percentageAvailable = 100 - $percentageUsed;
            
            $result['tokens_used'] = $tokensUsed;
            $result['token_limit'] = $tokenLimit;
            $result['percentage_used'] = round($percentageUsed, 1);
            $result['percentage_available'] = round($percentageAvailable, 1);
        }
    } else {
        $result['error'] = "No se encontró contenido en la respuesta";
        $result['response'] = $responseData;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>