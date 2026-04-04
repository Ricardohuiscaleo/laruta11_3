<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Configuración de base de datos desde Coolify
$config = require_once __DIR__ . '/config.php';

try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['cart'])) {
        throw new Exception("Datos de pedido inválidos");
    }

    $cart = $data['cart'];
    $total = $data['total'];

    // 1. Crear cabecera
    $stmt = $pdo->prepare("INSERT INTO tv_orders (total, status) VALUES (?, 'pendiente')");
    $stmt->execute([$total]);
    $orderId = $pdo->lastInsertId();

    // 2. Crear ítems
    $stmtItem = $pdo->prepare("INSERT INTO tv_order_items (order_id, product_id, product_name, price, customizations) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        $customs = isset($item['selections']) ? json_encode($item['selections']) : null;
        $stmtItem->execute([
            $orderId, 
            $item['id'], 
            $item['name'], 
            $item['price'], 
            $customs
        ]);
    }

    echo json_encode(['success' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
