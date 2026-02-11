<?php
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
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
    
    $start_date = $_GET['start_date'] ?? date('Y-m-d');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    
    // Obtener órdenes
    $sql = "
        SELECT 
            o.id,
            o.order_number,
            o.customer_name,
            o.customer_phone,
            o.created_at,
            o.payment_method,
            o.order_status,
            o.payment_status,
            COALESCE(o.tuu_amount, o.installment_amount) as total_amount,
            o.delivery_fee,
            o.delivery_type
        FROM tuu_orders o
        WHERE DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?
        AND o.payment_status = 'paid'
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Para cada orden, obtener items con inventario
    foreach ($orders as &$order) {
        // Obtener items
        $items_sql = "
            SELECT 
                oi.id,
                oi.product_id,
                oi.product_name,
                oi.quantity,
                oi.subtotal,
                oi.item_cost
            FROM tuu_order_items oi
            WHERE oi.order_reference = ?
        ";
        
        $items_stmt = $pdo->prepare($items_sql);
        $items_stmt->execute([$order['order_number']]);
        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener TODAS las transacciones de inventario de la orden (una sola vez)
        $trans_sql = "
            SELECT 
                it.id,
                it.ingredient_id,
                it.product_id,
                it.quantity,
                it.previous_stock,
                it.new_stock,
                COALESCE(i.name, p.name) as item_name,
                COALESCE(it.unit, i.unit, 'unidad') as unit,
                CASE WHEN it.ingredient_id IS NOT NULL THEN 'ingredient' ELSE 'product' END as item_type
            FROM inventory_transactions it
            LEFT JOIN ingredients i ON it.ingredient_id = i.id
            LEFT JOIN products p ON it.product_id = p.id
            WHERE it.order_reference = ?
            ORDER BY it.id ASC
        ";
        
        $trans_stmt = $pdo->prepare($trans_sql);
        $trans_stmt->execute([$order['order_number']]);
        $all_transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Asignar transacciones al primer item (para mostrar en la orden)
        foreach ($items as $index => &$item) {
            if ($index === 0 && count($all_transactions) > 0) {
                // Primer item: mostrar todas las transacciones de la orden
                $item['inventory_transactions'] = $all_transactions;
                $item['has_inventory_data'] = true;
            } else {
                // Otros items: no mostrar transacciones (ya se muestran en el primero)
                $item['inventory_transactions'] = [];
                $item['has_inventory_data'] = false;
            }
        }
        
        $order['items'] = $items;
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'orders' => $orders
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
