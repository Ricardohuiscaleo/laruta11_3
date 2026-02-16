<?php
// Archivo de diagnóstico - ELIMINAR después de revisar
header('Content-Type: application/json');

$config_file = __DIR__ . '/../../config.php';

$debug = [
    'config_file_path' => $config_file,
    'config_exists' => file_exists($config_file),
    'current_dir' => __DIR__,
    'config_content' => null,
    'config_keys' => null
];

if (file_exists($config_file)) {
    $config = require $config_file;
    $debug['config_content'] = $config;
    $debug['config_keys'] = array_keys($config);
    $debug['has_ruta11_keys'] = isset($config['ruta11_db_host']);
}

echo json_encode($debug, JSON_PRETTY_PRINT);
?>
