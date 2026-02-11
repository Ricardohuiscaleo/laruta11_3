<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $customer_name = $input['customer_name'] ?? 'Cliente Caja';
    $customer_phone = $input['customer_phone'] ?? '';
    $customer_notes = $input['customer_notes'] ?? '';
    $product_name = $input['product_name'] ?? '';
    $product_price = $input['product_price'] ?? 0;
    $installment_amount = $input['installment_amount'] ?? 0;
    $status = $input['status'] ?? 'pending';
    $payment_status = $input['payment_status'] ?? 'unpaid';
    $order_status = $input['order_status'] ?? 'pending';
    $delivery_type = $input['delivery_type'] ?? 'pickup';
    $has_item_details = $input['has_item_details'] ?? 1;
    $tuu_idempotency_key = $input['tuu_idempotency_key'] ?? null;
    $items = $input['items'] ?? [];
    
    $sql = "INSERT INTO tuu_orders (
        customer_name, 
        customer_phone, 
        customer_notes,
        product_name, 
        has_item_details,
        product_price, 
        installment_amount,
        tuu_idempotency_key,
        status, 
        payment_status, 
        order_status,
        delivery_type,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $customer_name,
        $customer_phone,
        $customer_notes,
        $product_name,
        $has_item_details,
        $product_price,
        $installment_amount,
        $tuu_idempotency_key,
        $status,
        $payment_status,
        $order_status,
        $delivery_type
    ]);
    
    $order_id = $pdo->lastInsertId();
    $order_number = "CAJA-{$order_id}";
    
    // Actualizar con el número de orden basado en el ID
    $update_sql = "UPDATE tuu_orders SET order_number = ? WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$order_number, $order_id]);
    
    // Guardar items individuales en tuu_order_items
    if (!empty($items) && is_array($items)) {
        $item_sql = "INSERT INTO tuu_order_items (
            order_id, order_reference, product_id, item_type, product_name, 
            product_price, item_cost, quantity, subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $pdo->prepare($item_sql);
        
        foreach ($items as $item) {
            $subtotal = $item['price'] * $item['cantidad'];
            $product_id = $item['id'] ?? null;
            
            // Calcular item_cost desde receta o cost_price
            $item_cost = 0;
            if ($product_id) {
                $recipe_stmt = $pdo->prepare("
                    SELECT SUM(i.cost_per_unit * pr.quantity * 
                        CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                    ) as recipe_cost
                    FROM product_recipes pr
                    JOIN ingredients i ON pr.ingredient_id = i.id
                    WHERE pr.product_id = ? AND i.is_active = 1
                ");
                $recipe_stmt->execute([$product_id]);
                $recipe_result = $recipe_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($recipe_result && $recipe_result['recipe_cost'] > 0) {
                    $item_cost = $recipe_result['recipe_cost'];
                } else {
                    $cost_stmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
                    $cost_stmt->execute([$product_id]);
                    $cost_result = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                    $item_cost = $cost_result['cost_price'] ?? 0;
                }
            }
            
            $item_stmt->execute([
                $order_id,
                $order_number,
                $product_id,
                'product',
                $item['name'],
                $item['price'],
                $item_cost,
                $item['cantidad'],
                $subtotal
            ]);
        }
    }
    
    // Determinar código HTTP según el estado del pago
    if ($payment_status === 'paid') {
        // Pago completado (efectivo)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Orden completada',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'status' => 'completed'
        ]);
    } else {
        // Orden creada, pendiente de pago (tarjeta)
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Orden creada - Pendiente de pago',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'status' => 'pending_payment'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>