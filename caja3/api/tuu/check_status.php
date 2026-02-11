<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

$status = [
    'api_status' => 'Error',
    'db_status' => 'Error', 
    'config_status' => 'Error',
    'all_ok' => false
];

// Verificar configuración
$config_ok = !empty($config['tuu_online_secret']) && !empty($config['tuu_api_key']);
$status['config_status'] = $config_ok ? 'OK' : 'Incompleta';

// Verificar base de datos
try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'tuu_orders'");
    $status['db_status'] = $stmt->rowCount() > 0 ? 'OK' : 'Tabla faltante';
} catch (Exception $e) {
    $status['db_status'] = 'Error conexión';
}

// Verificar API TUU (solo si config está OK)
if ($config_ok) {
    try {
        $url = 'https://frontend-api.payment.haulmer.dev/v1/payment/token/12345678-5';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer ' . $config['tuu_online_secret']
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $status['api_status'] = ($httpCode === 200) ? 'OK' : "HTTP $httpCode";
    } catch (Exception $e) {
        $status['api_status'] = 'Error conexión';
    }
}

$status['all_ok'] = ($status['api_status'] === 'OK' && $status['db_status'] === 'OK' && $status['config_status'] === 'OK');

echo json_encode($status);
?>