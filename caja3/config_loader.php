<?php
// Helper para cargar config.php desde cualquier ubicación
// Uso: $config = require_once __DIR__ . '/path/to/config_loader.php';

function loadConfig() {
    $levels = [
        __DIR__ . '/config.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/../../config.php',
        __DIR__ . '/../../../config.php',
        __DIR__ . '/../../../../config.php',
        __DIR__ . '/../../../../../config.php'
    ];
    
    foreach ($levels as $path) {
        if (file_exists($path)) {
            return require_once $path;
        }
    }
    
    throw new Exception('config.php no encontrado');
}

return loadConfig();
?>
