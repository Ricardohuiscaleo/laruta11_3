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
    foreach ($paths as $path) {
        if (file_exists($path)) {
            $config = require $path;
            break;
        }
    }
    
    if (!$config) {
        throw new Exception('Config file not found');
    }
    
    echo json_encode([
        'aws_key' => $config['aws_access_key_id'] ? 'OK' : 'MISSING',
        'aws_secret' => $config['aws_secret_access_key'] ? 'OK' : 'MISSING',
        'aws_region' => $config['aws_region'],
        's3_bucket' => $config['s3_bucket'],
        'google_maps' => $config['google_maps_api_key'] ? 'OK' : 'MISSING',
        'supabase' => $config['PUBLIC_SUPABASE_URL'] ? 'OK' : 'MISSING'
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>