<?php
$config = require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']}",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    
    $stmt = $pdo->query("
        SELECT 
            id,
            order_number,
            customer_data,
            items_data,
            total_amount,
            status,
            created_at
        FROM orders 
        WHERE status IN ('pending', 'paid') 
        ORDER BY created_at DESC
    ");
    
    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $orders[] = [
            'id' => $row['id'],
            'order_number' => $row['order_number'],
            'customer' => json_decode($row['customer_data'], true),
            'items' => json_decode($row['items_data'], true),
            'total' => (int)$row['total_amount'],
            'status' => $row['status'],
            'created_at' => date('d/m/Y H:i', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>