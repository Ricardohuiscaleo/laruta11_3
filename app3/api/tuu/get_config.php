<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
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

echo json_encode([
    'success' => true,
    'config' => [
        'rut' => $config['tuu_online_rut'] ?? '',
        'environment' => $config['tuu_online_env'] ?? 'development',
        'secret' => $config['tuu_online_secret'] ?? '',
        'api_key' => $config['tuu_api_key'] ?? ''
    ]
]);
?>