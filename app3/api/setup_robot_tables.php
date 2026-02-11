<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    echo json_encode(['success' => false, 'error' => 'Config file not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Tabla para logs del robot
    $sql1 = "CREATE TABLE IF NOT EXISTS robot_test_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        test_name VARCHAR(255) NOT NULL,
        status ENUM('success', 'failed', 'warning') NOT NULL,
        error_message TEXT NULL,
        severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
        execution_time INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_status BOOLEAN DEFAULT FALSE,
        INDEX idx_status (status),
        INDEX idx_created_at (created_at),
        INDEX idx_severity (severity)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Tabla para configuración del robot
    $sql2 = "CREATE TABLE IF NOT EXISTS robot_config (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        description TEXT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    // Tabla para estadísticas del robot
    $sql3 = "CREATE TABLE IF NOT EXISTS robot_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        date DATE NOT NULL,
        total_tests INT DEFAULT 0,
        successful_tests INT DEFAULT 0,
        failed_tests INT DEFAULT 0,
        avg_response_time DECIMAL(8,2) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_date (date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $pdo->exec($sql1);
    $pdo->exec($sql2);
    $pdo->exec($sql3);

    // Insertar configuración inicial
    $pdo->exec("INSERT IGNORE INTO robot_config (setting_name, setting_value, description) VALUES 
        ('robot_enabled', 'true', 'Enable/disable robot testing'),
        ('test_interval', '300', 'Test interval in seconds'),
        ('alert_email', 'admin@laruta11.cl', 'Email for critical alerts'),
        ('max_failures', '5', 'Max consecutive failures before alert')
    ");

    echo json_encode([
        'success' => true, 
        'message' => 'Robot tables created successfully',
        'tables' => ['robot_test_logs', 'robot_config', 'robot_stats']
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>