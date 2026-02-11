<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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
    
    $sql = "CREATE TABLE IF NOT EXISTS cash_register_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_date DATE NOT NULL,
        opened_at DATETIME NOT NULL,
        closed_at DATETIME NULL,
        opened_by VARCHAR(100) NOT NULL,
        closed_by VARCHAR(100) NULL,
        
        cash_total DECIMAL(10,2) DEFAULT 0,
        cash_count INT DEFAULT 0,
        card_total DECIMAL(10,2) DEFAULT 0,
        card_count INT DEFAULT 0,
        transfer_total DECIMAL(10,2) DEFAULT 0,
        transfer_count INT DEFAULT 0,
        pedidosya_total DECIMAL(10,2) DEFAULT 0,
        pedidosya_count INT DEFAULT 0,
        webpay_total DECIMAL(10,2) DEFAULT 0,
        webpay_count INT DEFAULT 0,
        
        total_amount DECIMAL(10,2) DEFAULT 0,
        total_orders INT DEFAULT 0,
        
        status ENUM('open', 'closed') DEFAULT 'open',
        
        opening_notes TEXT NULL,
        closing_notes TEXT NULL,
        
        whatsapp_sent TINYINT(1) DEFAULT 0,
        whatsapp_sent_at DATETIME NULL,
        
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_session_date (session_date),
        INDEX idx_status (status),
        INDEX idx_opened_at (opened_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $pdo->exec($sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla cash_register_sessions creada exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
