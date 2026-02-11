<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verificar sesión admin
if (!isset($_SESSION['keys_admin'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Buscar config.php hasta 5 niveles
function findConfig($startDir, $maxLevels = 5) {
    $currentDir = $startDir;
    for ($i = 0; $i < $maxLevels; $i++) {
        $configPath = $currentDir . '/config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
        $currentDir = dirname($currentDir);
    }
    return null;
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuración no encontrada']);
    exit;
}

$config = require $configPath;

// Filtrar keys sensibles para mostrar
$keysToShow = [
    'gemini_api_key',
    'unsplash_access_key',
    'ruta11_google_maps_api_key',
    'google_client_id',
    'google_client_secret',
    'ruta11_google_client_id',
    'ruta11_google_client_secret',
    'tuu_api_key',
    'tuu_online_secret',
    'tuu_device_serial',
    'aws_access_key_id',
    'aws_secret_access_key',
    's3_bucket',
    'app_db_host',
    'app_db_name',
    'app_db_user',
    'app_db_pass',
    'admin_users',
    'external_credentials'
];

$filteredKeys = [];
foreach ($keysToShow as $key) {
    if (isset($config[$key])) {
        $filteredKeys[$key] = $config[$key];
    }
}

echo json_encode(['success' => true, 'keys' => $filteredKeys]);
?>