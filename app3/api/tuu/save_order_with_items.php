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
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    $order_reference = $input['order_reference'] ?? '';
    $customer_name = $input['customer_name'] ?? '';
    $customer_phone = $input['customer_phone'] ?? '';
    $total_amount = $input['total_amount'] ?? 0;
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'] ?? [];
    
    if (empty($order_reference) || empty($cart_items)) {
        echo json_encode(['success' => false, 'error' => 'Referencia de pedido y items son requeridos']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // 1. Crear o actualizar el pedido principal
    $order_sql = "
        INSERT INTO tuu_orders (
            order_reference, 
            customer_name, 
            customer_phone, 
            product_name, 
            product_price,
            delivery_fee,
            tuu_amount,
            has_item_details,
            order_status,
            payment_status, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, 'pending', 'paid', NOW())
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            customer_phone = VALUES(customer_phone),
            product_name = VALUES(product_name),
            product_price = VALUES(product_price),
            delivery_fee = VALUES(delivery_fee),
            tuu_amount = VALUES(tuu_amount),
            has_item_details = TRUE,
            payment_status = 'paid'
    ";
    
    // Crear descripción de productos para el campo product_name
    $product_summary = count($cart_items) . ' productos: ' . 
        implode(', ', array_slice(array_map(function($item) {
            return $item['name'] . ' x' . $item['quantity'];
        }, $cart_items), 0, 3)) . 
        (count($cart_items) > 3 ? '...' : '');
    
    $stmt = $pdo->prepare($order_sql);
    $stmt->execute([
        $order_reference,
        $customer_name,
        $customer_phone,
        $product_summary,
        $total_amount,
        $delivery_fee,
        $total_amount
    ]);
    
    $order_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM tuu_orders WHERE order_reference = '$order_reference'")->fetchColumn();
    
    // 2. Limpiar items existentes (en caso de actualización)
    $delete_sql = "DELETE FROM tuu_order_items WHERE order_reference = ?";
    $stmt = $pdo->prepare($delete_sql);
    $stmt->execute([$order_reference]);
    
    // 3. Insertar items del carrito
    $item_sql = "
        INSERT INTO tuu_order_items (
            order_id, 
            order_reference, 
            product_id, 
            product_name, 
            product_price, 
            quantity, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $pdo->prepare($item_sql);
    
    foreach ($cart_items as $item) {
        $product_id = $item['id'] ?? null;
        $product_name = $item['name'] ?? 'Producto sin nombre';
        $product_price = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $subtotal = $product_price * $quantity;
        
        $stmt->execute([
            $order_id,
            $order_reference,
            $product_id,
            $product_name,
            $product_price,
            $quantity,
            $subtotal
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido guardado con detalle de productos',
        'order_id' => $order_id,
        'order_reference' => $order_reference,
        'items_count' => count($cart_items)
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>