<?php
header('Content-Type: application/json');

// Debug config paths
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
];

$debug = [];
$debug['current_dir'] = __DIR__;
$debug['paths_checked'] = [];

foreach ($config_paths as $path) {
    $debug['paths_checked'][] = [
        'path' => $path,
        'exists' => file_exists($path),
        'readable' => is_readable($path)
    ];
}

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        $debug['config_loaded_from'] = $path;
        break;
    }
}

$debug['config_loaded'] = $config !== null;
$debug['config_keys'] = $config ? array_keys($config) : [];

echo json_encode($debug, JSON_PRETTY_PRINT);
?>