<?php
header('Content-Type: application/json');

// Buscar config
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener configuración activa
    $stmt = $pdo->prepare("SELECT * FROM pos_config WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
    $stmt->execute();
    $posConfig = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($posConfig) {
        echo json_encode([
            'success' => true,
            'config' => [
                'serial' => $posConfig['serial_number'],
                'location' => $posConfig['location'],
                'operator' => $posConfig['operator_name'],
                'updated_at' => $posConfig['updated_at']
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'config' => null,
            'message' => 'No hay configuración guardada'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>