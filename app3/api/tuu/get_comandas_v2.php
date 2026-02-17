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
    
    $sql = "SELECT id, order_number, user_id, customer_name, customer_phone, table_number, 
                   product_name, has_item_details, product_price, installments_total, 
                   installment_current, installment_amount, tuu_payment_request_id, 
                   tuu_idempotency_key, tuu_device_used, status, payment_status, payment_method, order_status, 
                   delivery_type, delivery_address, pickup_time, customer_notes, 
                   subtotal, discount_amount, delivery_discount, delivery_extras, delivery_extras_items, cashback_used,
                   special_instructions, rider_id, estimated_delivery_time, created_at, updated_at, 
                   tuu_transaction_id, tuu_amount, tuu_timestamp, tuu_message, 
                   tuu_account_id, tuu_currency, tuu_signature, delivery_fee, 
                   scheduled_time, is_scheduled, reward_used, reward_stamps_consumed, reward_applied_at
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
    
    // Obtener items de cada pedido con imágenes y recetas
    foreach ($orders as &$order) {
        $items_stmt = $pdo->prepare("
            SELECT 
                oi.id, 
                oi.product_id, 
                oi.product_name, 
                oi.quantity, 
                oi.product_price, 
                oi.item_type, 
                oi.combo_data,
                p.image_url,
                p.category_id,
                p.description
            FROM tuu_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = ?
        ");
        $items_stmt->execute([$order['id']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener ingredientes de la receta para cada item
        foreach ($items as &$item) {
            if ($item['product_id']) {
                $recipeSql = "SELECT i.name, pr.quantity, pr.unit 
                             FROM product_recipes pr 
                             JOIN ingredients i ON pr.ingredient_id = i.id 
                             WHERE pr.product_id = ? AND i.is_active = 1
                             ORDER BY i.name";
                $recipeStmt = $pdo->prepare($recipeSql);
                $recipeStmt->execute([$item['product_id']]);
                $ingredients = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Crear descripción con ingredientes y cantidades
                if (!empty($ingredients)) {
                    $ingredientNames = array_map(function($ing) {
                        $qty = intval($ing['quantity']); // Sin decimales
                        $unit = strtolower($ing['unit']);
                        return $ing['name'] . ' (' . $qty . $unit . ')';
                    }, $ingredients);
                    $item['recipe_description'] = implode(', ', $ingredientNames);
                }
            }
        }
        
        $order['items'] = $items;
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
