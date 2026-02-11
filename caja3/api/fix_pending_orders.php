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
    
    // Actualizar solo las órdenes específicas 368-372
    $sql = "UPDATE tuu_orders 
            SET order_status = 'sent_to_kitchen', updated_at = CURRENT_TIMESTAMP 
            WHERE id IN (368, 369, 370, 371, 372)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Se actualizaron {$affected} órdenes específicas (368-372) a 'sent_to_kitchen'",
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
