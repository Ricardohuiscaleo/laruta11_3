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

    // Agregar columnas de snapshot de inventario
    $pdo->exec("ALTER TABLE compras_detalle 
        ADD COLUMN IF NOT EXISTS stock_antes DECIMAL(10,2) DEFAULT NULL COMMENT 'Inventario antes de la compra',
        ADD COLUMN IF NOT EXISTS stock_despues DECIMAL(10,2) DEFAULT NULL COMMENT 'Inventario después de la compra'
    ");

    echo json_encode([
        'success' => true,
        'message' => 'Columnas de snapshot agregadas a compras_detalle'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
