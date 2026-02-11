<?php
// Test de conexión a base de datos
header('Content-Type: application/json');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

echo json_encode([
    'config_found' => true,
    'config_path' => $configPath,
    'db_config' => [
        'host' => $config['app_db_host'],
        'name' => $config['app_db_name'],
        'user' => $config['app_db_user'],
        'pass_length' => strlen($config['app_db_pass'])
    ]
]);

try {
    // Intentar diferentes configuraciones de host
    $hosts = ['localhost', '127.0.0.1', 'localhost:3306'];
    
    foreach ($hosts as $host) {
        try {
            $dsn = "mysql:host={$host};dbname={$config['app_db_name']};charset=utf8mb4";
            $pdo = new PDO(
                $dsn,
                $config['app_db_user'],
                $config['app_db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            echo json_encode([
                'success' => true,
                'host_used' => $host,
                'connection' => 'OK',
                'server_info' => $pdo->getAttribute(PDO::ATTR_SERVER_INFO)
            ]);
            exit;
            
        } catch (Exception $e) {
            continue;
        }
    }
    
    throw new Exception('No se pudo conectar con ningún host');
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
?>