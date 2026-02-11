<?php
// Test simple para verificar configuración S3
header('Content-Type: application/json');

try {
    $config = require_once __DIR__ . '/../config.php';
    
    echo json_encode([
        'success' => true,
        'config_loaded' => true,
        'aws_key_exists' => !empty($config['aws_access_key_id']),
        'aws_secret_exists' => !empty($config['aws_secret_access_key']),
        'bucket' => $config['s3_bucket'],
        'region' => $config['aws_region'],
        'aws_key_preview' => substr($config['aws_access_key_id'], 0, 8) . '...',
        'debug' => 'Config test successful'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'debug' => 'Config test failed'
    ]);
}
?>