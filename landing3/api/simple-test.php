<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Usar la misma lógica robusta de s3-manager.php
    $paths = [
        __DIR__ . '/../config.php',
        dirname(dirname(dirname($_SERVER['DOCUMENT_ROOT']))) . '/config.php',
        dirname(dirname($_SERVER['DOCUMENT_ROOT'])) . '/config.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../config.php',
        $_SERVER['DOCUMENT_ROOT'] . '/../../config.php',
    ];
    
    $config = null;
    $configPath = 'NOT_FOUND';
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            $configPath = $path;
            break;
        }
    }
    
    if (!$config) {
        throw new Exception('Config file not found');
    }
    
    echo json_encode([
        'config_path' => basename(dirname($configPath)),
        'aws_key' => $config['aws_access_key_id'] ? 'OK - ' . substr($config['aws_access_key_id'], 0, 8) . '...' : 'MISSING',
        'aws_secret' => $config['aws_secret_access_key'] ? 'OK - ' . substr($config['aws_secret_access_key'], 0, 8) . '...' : 'MISSING',
        'aws_region' => $config['aws_region'],
        's3_bucket' => $config['s3_bucket']
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>