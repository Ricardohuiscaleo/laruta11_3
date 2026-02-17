<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
    
    // Filtro opcional por customer_name o user_id
    $customer_name = $_GET['customer_name'] ?? null;
    $user_id = $_GET['user_id'] ?? null;
    
    $where_clause = "WHERE order_status NOT IN ('cancelled') AND order_number NOT LIKE 'RL6-%'";
    
    if ($user_id) {
        $where_clause .= " AND user_id = :user_id";
    } elseif ($customer_name) {
        $where_clause .= " AND customer_name = :customer_name";
    }
    
    $sql = "SELECT id, order_number, user_id, customer_name, customer_phone, 
                   order_status, payment_status, payment_method, 
                   delivery_type, delivery_address, pickup_time, delivery_fee, installment_amount, 
                   customer_notes, discount_amount, cashback_used, delivery_extras, delivery_extras_items, 
                   delivery_discount, created_at
            FROM tuu_orders 
            {$where_clause}
            ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    
    if ($user_id) {
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    } elseif ($customer_name) {
        $stmt->bindParam(':customer_name', $customer_name);
    }
    
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener items de cada pedido
    foreach ($orders as &$order) {
        $items_stmt = $pdo->prepare("
            SELECT id, product_name, quantity, product_price, item_type, combo_data
            FROM tuu_order_items
            WHERE order_id = ?
        ");
        $items_stmt->execute([$order['id']]);
        $order['items'] = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
