<?php
header('Content-Type: application/json');

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
    
    $order_ref = $_GET['order'] ?? '';
    
    if (empty($order_ref)) {
        echo json_encode(['success' => false, 'error' => 'Order reference requerido']);
        exit;
    }
    
    // Obtener pedido principal
    $order_sql = "SELECT * FROM tuu_orders WHERE order_number = ? OR order_reference = ?";
    $stmt = $pdo->prepare($order_sql);
    $stmt->execute([$order_ref, $order_ref]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }
    
    // Obtener items del pedido si existen
    $items = [];
    if ($order['has_item_details']) {
        $items_sql = "SELECT * FROM tuu_order_items WHERE order_reference = ? ORDER BY id";
        $stmt = $pdo->prepare($items_sql);
        $stmt->execute([$order_ref]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'order' => [
            'reference' => $order['order_number'],
            'amount' => $order['tuu_amount'] ?: $order['product_price'],
            'customer_name' => $order['customer_name'],
            'customer_phone' => $order['customer_phone'],
            'customer_address' => 'Dirección no especificada', // TODO: agregar campo address
            'status' => $order['status'],
            'has_items' => $order['has_item_details'] == 1,
            'created_at' => $order['created_at']
        ],
        'items' => $items
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>