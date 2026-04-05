<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $dsn = "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['app_db_user'], $config['app_db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['cart'])) {
        throw new Exception("Datos de pedido inválidos");
    }

    $cart = $data['cart'];
    $total = $data['total'];

    $stmt = $pdo->prepare("INSERT INTO tv_orders (total, status) VALUES (?, 'pendiente')");
    $stmt->execute([$total]);
    $orderId = $pdo->lastInsertId();

    $stmtItem = $pdo->prepare("INSERT INTO tv_order_items (order_id, product_id, product_name, price, customizations) VALUES (?, ?, ?, ?, ?)");
    foreach ($cart as $item) {
        // Guardar todo el item como JSON para reconstruir el carrito exactamente
        $fullItemData = json_encode([
            'selections'    => $item['selections'] ?? null,
            'fixed_items'   => $item['fixed_items'] ?? null,
            'category_name' => $item['category_name'] ?? null,
            'type'          => $item['type'] ?? null,
            'displayName'   => $item['displayName'] ?? null,
        ]);
        $stmtItem->execute([
            $orderId,
            $item['id'],
            $item['name'],
            $item['price'],
            $fullItemData
        ]);
    }

    echo json_encode(['success' => true, 'order_id' => $orderId]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
