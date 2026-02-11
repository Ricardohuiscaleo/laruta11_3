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
    
    // Crear tabla para pagos remotos
    $sql = "
    CREATE TABLE IF NOT EXISTS tuu_remote_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        payment_id VARCHAR(100) NOT NULL,
        idempotency_key VARCHAR(36) NOT NULL,
        amount INT NOT NULL,
        device VARCHAR(50) NOT NULL,
        status ENUM('pending', 'sent', 'processing', 'completed', 'failed', 'canceled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        request_data JSON,
        response_data JSON,
        tuu_response JSON,
        UNIQUE KEY unique_payment_id (payment_id),
        UNIQUE KEY unique_idempotency (idempotency_key),
        INDEX idx_status (status),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla tuu_remote_payments creada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>