<?php
set_time_limit(60);
ini_set('max_execution_time', 60);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/config.php',
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

    // Crear tabla de cajeros
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cashiers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(100) NOT NULL,
            phone VARCHAR(20) NULL,
            email VARCHAR(100) NULL,
            role ENUM('cajero', 'admin') DEFAULT 'cajero',
            active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insertar datos iniciales
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO cashiers (username, password, full_name, role) VALUES
        ('cajera', 'ruta11caja', 'Tami', 'cajero'),
        ('admin', 'admin123', 'Administrador', 'admin')
    ");
    $stmt->execute();

    // Agregar columnas a tuu_orders si no existen
    try {
        $pdo->exec("ALTER TABLE tuu_orders ADD COLUMN cashier_id INT NULL AFTER customer_notes");
    } catch (Exception $e) {
        // Columna ya existe
    }

    try {
        $pdo->exec("ALTER TABLE tuu_orders ADD COLUMN cashier_name VARCHAR(100) NULL AFTER cashier_id");
    } catch (Exception $e) {
        // Columna ya existe
    }

    echo json_encode([
        'success' => true,
        'message' => 'Tabla de cajeros creada exitosamente'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
