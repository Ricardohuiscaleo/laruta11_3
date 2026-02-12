<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../config.php',        // 1 nivel
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
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

try {
    // Probar conexión a base de datos app
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Hacer una consulta simple para verificar conexión
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    
    if ($result['test'] === 1) {
        echo json_encode([
            'success' => true,
            'message' => 'Conexión exitosa a base de datos',
            'database' => $config['app_db_name'],
            'host' => $config['app_db_host']
        ]);
    } else {
        throw new Exception('Consulta de prueba falló');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>