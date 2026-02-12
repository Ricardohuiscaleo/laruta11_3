<?php
header('Content-Type: application/json');

// Buscar config.php en múltiples niveles
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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

// Verificar la configuración
$result = [
    'success' => true,
    'database' => [
        'connected' => false,
        'error' => null
    ],
    'gemini_api' => [
        'configured' => false,
        'key_length' => 0
    ],
    'tables' => [
        'ia_prompts' => false,
        'ia_analisis' => false
    ],
    'php_version' => PHP_VERSION,
    'curl_enabled' => function_exists('curl_init'),
    'json_enabled' => function_exists('json_encode')
];

// Verificar conexión a la base de datos APP
$conn = null;
if (isset($config['app_db_host'], $config['app_db_name'], $config['app_db_user'], $config['app_db_pass'])) {
    try {
        $conn = @mysqli_connect(
            $config['app_db_host'],
            $config['app_db_user'],
            $config['app_db_pass'],
            $config['app_db_name']
        );
        if ($conn) {
            $result['database']['connected'] = true;
        } else {
            $result['database']['error'] = mysqli_connect_error();
        }
    } catch (Exception $e) {
        $result['database']['error'] = $e->getMessage();
    }
} else {
    $result['database']['note'] = 'APP_DB credentials not configured';
}

if (isset($config['gemini_api_key']) && !empty($config['gemini_api_key'])) {
    $result['gemini_api']['configured'] = true;
    $result['gemini_api']['key_length'] = strlen($config['gemini_api_key']);
    
    // Verificar los primeros y últimos caracteres (sin revelar la clave completa)
    $key = $config['gemini_api_key'];
    $result['gemini_api']['key_start'] = substr($key, 0, 5) . '...';
    $result['gemini_api']['key_end'] = '...' . substr($key, -5);
} else {
    $result['success'] = false;
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>