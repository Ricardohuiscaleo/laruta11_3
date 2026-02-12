<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

echo json_encode([
    'test' => 'PHP funciona',
    'env_test' => getenv('DB_HOST') ?: 'NO_ENV',
    'config_exists' => file_exists(__DIR__ . '/../config.php'),
    'load_env_exists' => file_exists(__DIR__ . '/../load-env.php')
]);

// Intentar cargar config
try {
    require_once __DIR__ . '/../config.php';
    echo json_encode(['config_loaded' => 'OK']);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
