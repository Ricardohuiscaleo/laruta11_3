<?php
header('Content-Type: application/json');

// Buscar config.php
$configPaths = ['../config.php', '../../config.php', '../../../config.php'];
$configFound = false;
foreach ($configPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config = require $path;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

// Test básico de conectividad
$testUrl = "https://{$config['s3_bucket']}.s3.{$config['s3_region']}.amazonaws.com/";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $testUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_NOBODY => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo json_encode([
    'bucket' => $config['s3_bucket'],
    'region' => $config['s3_region'],
    'test_url' => $testUrl,
    'http_code' => $httpCode,
    'curl_error' => $curlError,
    'response' => $response === false ? 'false' : 'success',
    'access_key' => substr($config['aws_access_key_id'], 0, 8) . '...'
]);
?>