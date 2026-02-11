<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Actualizar la orden específica R11-1758638226-9313
    $sql = "UPDATE tuu_orders SET payment_status = 'paid' WHERE order_number = 'R11-1758638226-9313'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => 'Orden R11-1758638226-9313 actualizada a payment_status = paid',
        'affected_rows' => $stmt->rowCount()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>