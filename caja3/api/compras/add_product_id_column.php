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
    
    // Agregar columna product_id si no existe
    $pdo->exec("ALTER TABLE compras_detalle ADD COLUMN IF NOT EXISTS product_id INT NULL AFTER ingrediente_id");
    
    // Agregar columna item_type si no existe
    $pdo->exec("ALTER TABLE compras_detalle ADD COLUMN IF NOT EXISTS item_type ENUM('ingredient', 'product') DEFAULT 'ingredient' AFTER product_id");
    
    echo json_encode([
        'success' => true,
        'message' => 'Columnas product_id e item_type agregadas correctamente'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
