<?php
header('Content-Type: application/json');

// Buscar config.php
$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php'
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
    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar si la columna ya existe
    $check_sql = "SHOW COLUMNS FROM tuu_orders LIKE 'user_id'";
    $check_result = $pdo->query($check_sql);
    
    if ($check_result->rowCount() > 0) {
        echo json_encode([
            'success' => true, 
            'message' => 'La columna user_id ya existe en tuu_orders'
        ]);
        exit;
    }

    // Agregar columna user_id
    $alter_sql = "ALTER TABLE tuu_orders 
                  ADD COLUMN user_id INT NULL AFTER order_number,
                  ADD INDEX idx_user_id (user_id)";
    
    $pdo->exec($alter_sql);
    
    echo json_encode([
        'success' => true,
        'message' => 'Columna user_id agregada exitosamente a tuu_orders'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en migración: ' . $e->getMessage()
    ]);
}
?>