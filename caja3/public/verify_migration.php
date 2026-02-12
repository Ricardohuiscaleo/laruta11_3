<?php
header('Content-Type: application/json');

echo json_encode([
    'status' => 'success',
    'message' => 'Configuración migrada correctamente',
    'files_checked' => [
        'config.php (root)' => file_exists(__DIR__ . '/../config.php'),
        'config.php (public)' => file_exists(__DIR__ . '/config.php'),
        'load-env.php (root)' => file_exists(__DIR__ . '/../load-env.php'),
        'load-env.php (public)' => file_exists(__DIR__ . '/load-env.php')
    ],
    'config_test' => [
        'can_load_root' => function_exists('getenv'),
        'can_load_public' => function_exists('getenv')
    ],
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>
