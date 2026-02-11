<?php
require_once '../config.php';

// Verificar que la clave API esté configurada
if (!isset($config['gemini_api_key']) || empty($config['gemini_api_key'])) {
    die('La clave API de Gemini no está configurada');
}

$apiKey = $config['gemini_api_key'];

// Obtener información de modelos disponibles
$modelsUrl = "https://generativelanguage.googleapis.com/v1/models?key=" . $apiKey;
$ch = curl_init($modelsUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$modelsResponse = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

$modelsData = [];
$errorMessage = '';

if ($error) {
    $errorMessage = "Error de cURL: $error";
} else {
    $modelsData = json_decode($modelsResponse, true);
    
    if ($httpCode != 200) {
        $errorMessage = "Error HTTP: $httpCode";
    }
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
$usageMetadata = isset($testData['usageMetadata']) ? $testData['usageMetadata'] : null;

// Modelos recomendados
$recommendedModels = [
    [
        'nombre' => 'gemini-2.5-flash',
        'descripcion' => 'Modelo más reciente y avanzado, gratuito con límites',
        'tokens_entrada' => '1,048,576',
        'tokens_salida' => '65,536',
        'recomendado' => true,
        'caracteristicas' => ['Capacidad de pensamiento', 'Mayor contexto de salida', 'Mejor calidad de respuesta']
    ],
    [
        'nombre' => 'gemini-2.0-flash',
        'descripcion' => 'Modelo anterior, también gratuito con límites',
        'tokens_entrada' => '1,048,576',
        'tokens_salida' => '8,192',
        'caracteristicas' => ['Buen equilibrio entre velocidad y calidad']
    ],
    [
        'nombre' => 'gemini-1.5-flash',
        'descripcion' => 'Modelo más antiguo, compatible con la API actual',
        'tokens_entrada' => '1,000,000',
        'tokens_salida' => '8,192',
        'caracteristicas' => ['Mayor compatibilidad']
    ]
];

// Filtrar modelos disponibles
$availableModels = [];
if (isset($modelsData['models'])) {
    foreach ($modelsData['models'] as $model) {
        if (strpos($model['name'], 'gemini') !== false) {
            $availableModels[] = $model;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Información de Cuota de Gemini API</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">Información de Cuota de Gemini API</h1>
        
        <?php if ($errorMessage): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>
        
        <!-- Información de la clave API -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Información de la Clave API</h2>
            <p><strong>Prefijo de clave:</strong> <?php echo substr($apiKey, 0, 5) . '...' . substr($apiKey, -5); ?></p>
            <p><strong>Longitud:</strong> <?php echo strlen($apiKey); ?> caracteres</p>
        </div>
        
        <!-- Información de uso actual -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Uso Actual</h2>
            <?php if ($usageMetadata): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Métrica</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($usageMetadata as $key => $value): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo $key; ?></td>
                                    <td class="py-2 px-4 border-b border-gray-200"><?php echo is_array($value) ? json_encode($value) : $value; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No hay información de uso disponible.</p>
            <?php endif; ?>
        </div>
        
        <!-- Modelos recomendados -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-semibold mb-4">Modelos Recomendados</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($recommendedModels as $model): ?>
                    <div class="border rounded-lg p-4 <?php echo isset($model['recomendado']) && $model['recomendado'] ? 'border-green-500 bg-green-50' : 'border-gray-200'; ?>">
                        <h3 class="font-semibold text-lg mb-2"><?php echo $model['nombre']; ?></h3>
                        <p class="text-sm text-gray-600 mb-2"><?php echo $model['descripcion']; ?></p>
                        <div class="text-xs text-gray-500">
                            <p><strong>Tokens de entrada:</strong> <?php echo $model['tokens_entrada']; ?></p>
                            <p><strong>Tokens de salida:</strong> <?php echo $model['tokens_salida']; ?></p>
                        </div>
                        <?php if (isset($model['caracteristicas']) && !empty($model['caracteristicas'])): ?>
                            <div class="mt-2">
                                <p class="text-xs font-medium text-gray-700 mb-1">Características:</p>
                                <ul class="text-xs text-gray-600 list-disc pl-4">
                                    <?php foreach ($model['caracteristicas'] as $caracteristica): ?>
                                        <li><?php echo $caracteristica; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($model['recomendado']) && $model['recomendado']): ?>
                            <div class="mt-2">
                                <span class="inline-block bg-green-100 text-green-800 text-xs px-2 py-1 rounded-full">Recomendado</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Modelos disponibles -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-semibold mb-4">Modelos Disponibles (<?php echo count($availableModels); ?>)</h2>
            <?php if (!empty($availableModels)): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead>
                            <tr>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Nombre</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Versión</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tokens Entrada</th>
                                <th class="py-2 px-4 border-b border-gray-200 bg-gray-50 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tokens Salida</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($availableModels as $model): ?>
                                <tr>
                                    <td class="py-2 px-4 border-b border-gray-200 font-medium">
                                        <?php echo $model['displayName']; ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <?php echo $model['version']; ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <?php echo number_format($model['inputTokenLimit'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="py-2 px-4 border-b border-gray-200">
                                        <?php echo number_format($model['outputTokenLimit'], 0, ',', '.'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500">No hay modelos disponibles.</p>
            <?php endif; ?>
        </div>
        
        <div class="mt-6 text-center text-sm text-gray-500">
            <p>Límites de la capa gratuita: 60 solicitudes por minuto / 1,000,000 caracteres por minuto</p>
            <p>Aproximadamente 30,000,000 caracteres por mes</p>
        </div>
    </div>
</body>
</html>