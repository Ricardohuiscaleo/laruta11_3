<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

try {
    $config = require_once __DIR__ . '/config.php';
    
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $input = json_decode(file_get_contents('php://input'), true);
    $cart = $input['cart'] ?? [];
    $total = $input['total'] ?? 0;

    if (empty($cart)) {
        echo json_encode(['success' => false, 'error' => 'Carrito vacío']);
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO tv_orders (total, status, created_at) VALUES (?, 'pendiente', NOW())");
    $stmt->execute([$total]);
    $orderId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO tv_order_items (order_id, product_id, product_name, price, customizations) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($cart as $item) {
        $customizations = isset($item['selections']) ? json_encode($item['selections']) : null;
        $stmtItem->execute([
            $orderId,
            $item['id'],
            $item['name'],
            $item['price'],
            $customizations
        ]);
    }

    echo json_encode(['success' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
