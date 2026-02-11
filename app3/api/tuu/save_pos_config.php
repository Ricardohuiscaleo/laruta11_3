<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Crear tabla de configuración si no existe
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pos_config (
            id INT AUTO_INCREMENT PRIMARY KEY,
            serial_number VARCHAR(50) NOT NULL,
            location VARCHAR(100),
            operator_name VARCHAR(100),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_serial (serial_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insertar o actualizar configuración
    $stmt = $pdo->prepare("
        INSERT INTO pos_config (serial_number, location, operator_name) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        location = VALUES(location),
        operator_name = VALUES(operator_name),
        updated_at = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $input['serial'] ?? '',
        $input['location'] ?? '',
        $input['operator'] ?? ''
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Configuración POS guardada correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>