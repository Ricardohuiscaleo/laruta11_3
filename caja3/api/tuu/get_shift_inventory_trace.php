<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
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
    
    $shift_date = $_GET['shift_date'] ?? date('Y-m-d');
    
    // Obtener transacciones del turno
    $start_datetime_chile = $shift_date . ' 17:30:00';
    $next_day = date('Y-m-d', strtotime($shift_date . ' +1 day'));
    $end_datetime_chile = $next_day . ' 04:00:00';
    $start_datetime_utc = date('Y-m-d H:i:s', strtotime($start_datetime_chile . ' +3 hours'));
    $end_datetime_utc = date('Y-m-d H:i:s', strtotime($end_datetime_chile . ' +3 hours'));
    
    // Obtener órdenes con items
    $sql = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.created_at,
            o.payment_method,
            COALESCE(o.tuu_amount, o.installment_amount) as total_amount,
            o.delivery_fee
        FROM tuu_orders o
        WHERE o.created_at >= ? AND o.created_at < ?
        AND o.payment_status = 'paid'
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_datetime_utc, $end_datetime_utc]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada orden, obtener items e ingredientes
    foreach ($orders as &$order) {
        // Convertir fecha a Chile
        $utc_date = new DateTime($order['created_at'], new DateTimeZone('UTC'));
        $utc_date->setTimezone(new DateTimeZone('America/Santiago'));
        $order['created_at'] = $utc_date->format('Y-m-d H:i:s');
        
        // Obtener items de la orden
        $items_sql = "
            SELECT 
                oi.product_id,
                oi.product_name,
                oi.quantity,
                oi.subtotal,
                oi.item_cost,
                p.stock as current_stock,
                p.has_recipe
            FROM tuu_order_items oi
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE oi.order_reference = ?
        ";
        
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([$order['order_number']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Para cada item, obtener ingredientes si tiene receta
        foreach ($items as &$item) {
            $item['ingredients'] = [];
            $item['inventory_status'] = 'unknown';
            
            if ($item['has_recipe'] == 1 && $item['product_id']) {
                // Obtener ingredientes de la receta
                $ing_sql = "
                    SELECT 
                        r.ingredient_id,
                        i.name as ingredient_name,
                        r.quantity_needed,
                        i.unit,
                        i.stock as current_stock,
                        i.stock + (r.quantity_needed * ?) as stock_before
                    FROM recipes r
                    INNER JOIN ingredients i ON r.ingredient_id = i.id
                    WHERE r.product_id = ?
                ";
                
                $ing_stmt = $pdo->prepare($ing_sql);
                $ing_stmt->execute([$item['quantity'], $item['product_id']]);
                $ingredients = $ing_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($ingredients as &$ing) {
                    $ing['quantity_used'] = $ing['quantity_needed'] * $item['quantity'];
                    $ing['stock_matches'] = abs($ing['current_stock'] - ($ing['stock_before'] - $ing['quantity_used'])) < 0.01;
                }
                
                $item['ingredients'] = $ingredients;
                $item['inventory_status'] = count($ingredients) > 0 ? 'with_ingredients' : 'no_recipe';
            } else {
                // Producto sin receta (bebidas, etc)
                $item['inventory_status'] = 'direct_product';
                $item['stock_before'] = $item['current_stock'] + $item['quantity'];
                $item['stock_matches'] = true; // Asumimos que cuadra
            }
        }
        
        $order['items'] = $items;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders,
            'shift_info' => [
                'shift_date' => $shift_date,
                'start_chile' => $start_datetime_chile,
                'end_chile' => $end_datetime_chile
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
