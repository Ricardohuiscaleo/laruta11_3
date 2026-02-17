<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

try {
    $order_id = $_GET['order'] ?? null;
    
    if (!$order_id || !str_starts_with($order_id, 'RL6-')) {
        throw new Exception('Order ID RL6 requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "SELECT status, payment_status, product_price FROM tuu_orders WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Orden no encontrada');
    }
    
    echo json_encode([
        'success' => true,
        'status' => $order['status'],
        'payment_status' => $order['payment_status'],
        'amount' => $order['product_price']
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
