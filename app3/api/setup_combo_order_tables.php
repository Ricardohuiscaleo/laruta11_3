<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
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

    // Verificar si la columna combo_details existe en tuu_order_items
    $check_column = "SHOW COLUMNS FROM tuu_order_items LIKE 'combo_details'";
    $result = $pdo->query($check_column);
    
    if ($result->rowCount() == 0) {
        // Agregar columna combo_details
        $add_column = "ALTER TABLE tuu_order_items ADD COLUMN combo_details JSON NULL AFTER subtotal";
        $pdo->exec($add_column);
        echo json_encode(['success' => true, 'message' => 'Columna combo_details agregada']);
    } else {
        echo json_encode(['success' => true, 'message' => 'Columna combo_details ya existe']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>