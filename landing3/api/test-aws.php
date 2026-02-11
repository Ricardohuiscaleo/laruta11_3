<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Cargar configuración
    require_once __DIR__ . '/../load-env.php';
    $config = include __DIR__ . '/../config.php';
    
    $results = [];
    
    // Test básico
    $results['env_file'] = file_exists(__DIR__ . '/../.env') ? 'OK' : 'ERROR';
    $results['aws_key'] = $config['aws_access_key_id'] ? 'OK - ' . substr($config['aws_access_key_id'], 0, 8) . '...' : 'MISSING';
    $results['aws_secret'] = $config['aws_secret_access_key'] ? 'OK - ' . substr($config['aws_secret_access_key'], 0, 8) . '...' : 'MISSING';
    $results['aws_region'] = $config['aws_region'];
    $results['s3_bucket'] = $config['s3_bucket'];
    
    echo json_encode($results, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
?>