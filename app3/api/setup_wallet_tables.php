<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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
    
    // Tabla de saldo de usuarios
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_wallet (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            balance DECIMAL(10,2) DEFAULT 0 COMMENT 'Saldo disponible',
            total_earned DECIMAL(10,2) DEFAULT 0 COMMENT 'Total ganado historico',
            total_used DECIMAL(10,2) DEFAULT 0 COMMENT 'Total usado historico',
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Tabla de transacciones de wallet
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wallet_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('earned', 'used') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            order_id VARCHAR(50),
            description TEXT,
            balance_before DECIMAL(10,2),
            balance_after DECIMAL(10,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_date (user_id, created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Columna para rastrear cashback generado por nivel
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN IF NOT EXISTS cashback_level_bronze TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS cashback_level_silver TINYINT(1) DEFAULT 0,
        ADD COLUMN IF NOT EXISTS cashback_level_gold TINYINT(1) DEFAULT 0
    ");
    
    echo json_encode([
        'success' => true,
        'message' => 'Tablas de wallet creadas exitosamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
