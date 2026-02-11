<?php
header('Content-Type: application/json');
require_once '../config.php';

// Verificar que la clave API esté configurada
if (!isset($config['gemini_api_key']) || empty($config['gemini_api_key'])) {
    echo json_encode(['success' => false, 'error' => 'La clave API de Gemini no está configurada']);
    exit;
}

$apiKey = $config['gemini_api_key'];

// Función para obtener información de uso de la API
function getApiUsage($apiKey) {
    // Extraer el ID del proyecto de la clave API (si es posible)
    $keyParts = explode('-', $apiKey);
    $projectId = isset($keyParts[0]) ? $keyParts[0] : null;
    
    // Realizar una solicitud de prueba para obtener encabezados de respuesta
    $url = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    // Extraer información de cuota de los encabezados
    $quotaInfo = [];
    
    // Extraer encabezados X-RateLimit si existen
    if (preg_match('/X-RateLimit-Limit: (\d+)/i', $headers, $matches)) {
        $quotaInfo['limit'] = (int)$matches[1];
    }
    
    if (preg_match('/X-RateLimit-Remaining: (\d+)/i', $headers, $matches)) {
        $quotaInfo['remaining'] = (int)$matches[1];
    }
    
    if (preg_match('/X-RateLimit-Reset: (\d+)/i', $headers, $matches)) {
        $quotaInfo['reset'] = (int)$matches[1];
    }
    
    // Realizar una solicitud de prueba para obtener información de uso
    $testUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=" . $apiKey;
    $testData = [
        "contents" => [
            [
                "parts" => [
                    [
                        "text" => "Hola"
                    ]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.1,
            "maxOutputTokens" => 10
        ]
    ];
    
    $ch = curl_init($testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $testResponse = curl_exec($ch);
    curl_close($ch);
    
    $testData = json_decode($testResponse, true);
    
    // Extraer información de tokens si está disponible
    if (isset($testData['usageMetadata'])) {
        $quotaInfo['usage'] = $testData['usageMetadata'];
    }
    
    // Obtener información de uso de la API desde Google Cloud (si está disponible)
    $cloudApiUrl = "https://generativelanguage.googleapis.com/v1/operations?key=" . $apiKey;
    $ch = curl_init($cloudApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $cloudResponse = curl_exec($ch);
    curl_close($ch);
    
    $cloudData = json_decode($cloudResponse, true);
    if (isset($cloudData['operations'])) {
        $quotaInfo['operations'] = $cloudData['operations'];
    }
    
    return [
        'quota_info' => $quotaInfo,
        'project_id' => $projectId,
        'api_key_prefix' => substr($apiKey, 0, 5) . '...' . substr($apiKey, -5)
    ];
}

// Obtener información de modelos disponibles
$modelsUrl = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;
$ch = curl_init($modelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$modelsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$result = [
    'success' => false,
    'api_key_configured' => !empty($apiKey),
    'api_key_length' => strlen($apiKey),
    'http_code' => $httpCode
];

if ($error) {
    $result['error'] = "Error de cURL: $error";
} else {
    $modelsData = json_decode($modelsResponse, true);
    
    if ($httpCode != 200) {
        $result['error'] = "Error HTTP: $httpCode";
        $result['response'] = $modelsData;
    } else {
        // Obtener información de uso
        $usageInfo = getApiUsage($apiKey);
        
        $result['success'] = true;
        $result['models'] = isset($modelsData['models']) ? $modelsData['models'] : [];
        $result['usage_info'] = $usageInfo;
        
        // Información adicional sobre límites de Gemini API
        $result['quota_info'] = [
            'gemini_free_tier' => [
                'description' => 'Límites de la capa gratuita de Gemini API',
                'daily_limit' => '60 solicitudes por minuto / 1,000,000 caracteres por minuto',
                'monthly_limit' => 'Aproximadamente 30,000,000 caracteres por mes',
                'note' => 'Los límites exactos pueden variar. Consulta la documentación oficial para más detalles.'
            ],
            'modelos_recomendados' => [
                [
                    'nombre' => 'gemini-2.5-flash',
                    'descripcion' => 'Modelo más reciente y avanzado, gratuito con límites',
                    'tokens_entrada' => '1,048,576',
                    'tokens_salida' => '65,536',
                    'recomendado' => true
                ],
                [
                    'nombre' => 'gemini-2.0-flash',
                    'descripcion' => 'Modelo anterior, también gratuito con límites',
                    'tokens_entrada' => '1,048,576',
                    'tokens_salida' => '8,192'
                ],
                [
                    'nombre' => 'gemini-1.5-flash',
                    'descripcion' => 'Modelo más antiguo, compatible con la API actual',
                    'tokens_entrada' => '1,000,000',
                    'tokens_salida' => '8,192'
                ]
            ]
        ];
    }
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>