<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://app.laruta11.cl');

// Buscar config.php en múltiples niveles
$config_paths = [
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

    // Actualizar pedidos con transaction_status = 'completed' pero payment_status = 'unpaid'
    $stmt = $pdo->prepare("
        UPDATE tuu_orders 
        SET payment_status = 'paid' 
        WHERE transaction_status = 'completed' 
        AND payment_status = 'unpaid'
    ");
    
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Se actualizaron $affected pedidos a payment_status = 'paid'",
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>