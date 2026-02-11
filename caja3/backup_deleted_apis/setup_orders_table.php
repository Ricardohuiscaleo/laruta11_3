<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se encontró el archivo de configuración']);
        exit;
    }
}

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Crear tabla de pedidos
    $sql = "CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        customer_name VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20),
        table_number VARCHAR(10),
        product_name VARCHAR(255) NOT NULL,
        product_price DECIMAL(10,2) NOT NULL,
        installments_total INT DEFAULT 1,
        installment_current INT DEFAULT 1,
        installment_amount DECIMAL(10,2) NOT NULL,
        tuu_payment_request_id INT,
        tuu_idempotency_key VARCHAR(255),
        tuu_device_used VARCHAR(50),
        status ENUM('pending', 'sent_to_pos', 'completed', 'failed') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla orders creada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>