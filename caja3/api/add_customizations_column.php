<?php
// Script para agregar columna customizations a tuu_order_items

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
    die("Config no encontrado\n");
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar si la columna ya existe
    $check = $pdo->query("SHOW COLUMNS FROM tuu_order_items LIKE 'customizations'");
    if ($check->rowCount() > 0) {
        echo "✓ La columna 'customizations' ya existe en tuu_order_items\n";
        exit;
    }
    
    // Agregar columna customizations
    $pdo->exec("
        ALTER TABLE tuu_order_items 
        ADD COLUMN customizations JSON NULL AFTER combo_data
    ");
    
    echo "✓ Columna 'customizations' agregada exitosamente a tuu_order_items\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
