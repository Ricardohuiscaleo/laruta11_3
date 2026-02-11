<?php
header('Content-Type: application/json');

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
    
    // Recibir fecha del turno (ej: 2025-11-08)
    $shift_date = $_GET['shift_date'] ?? date('Y-m-d');
    
    // Turno: 17:30 del shift_date hasta 04:00 del día siguiente
    $start_datetime_chile = $shift_date . ' 17:30:00';
    $next_day = date('Y-m-d', strtotime($shift_date . ' +1 day'));
    $end_datetime_chile = $next_day . ' 04:00:00';
    
    // Convertir a UTC (Chile = UTC-3, sumar 3 horas)
    $start_datetime_utc = date('Y-m-d H:i:s', strtotime($start_datetime_chile . ' +3 hours'));
    $end_datetime_utc = date('Y-m-d H:i:s', strtotime($end_datetime_chile . ' +3 hours'));
    
    // Consulta con rango UTC
    $sql = "
        SELECT 
            o.id,
            o.order_number as order_reference,
            COALESCE(o.tuu_amount, o.installment_amount, o.product_price) as amount,
            COALESCE(o.payment_status, o.status) as status,
            o.order_status,
            o.customer_name,
            o.customer_phone,
            o.product_name,
            o.created_at,
            o.tuu_transaction_id,
            o.payment_method,
            o.delivery_type,
            COALESCE(o.delivery_fee, 0) as delivery_fee,
            COALESCE(
                (SELECT SUM(item_cost * quantity) 
                 FROM tuu_order_items 
                 WHERE order_reference = o.order_number),
                0
            ) as order_cost,
            CASE 
                WHEN o.payment_method = 'webpay' THEN 'app'
                WHEN o.payment_method IN ('cash', 'card', 'transfer') THEN 'caja'
                WHEN o.payment_method = 'pedidosya' THEN 'pedidosya'
                ELSE 'online'
            END as payment_source
        FROM tuu_orders o
        WHERE o.created_at >= ? AND o.created_at < ?
        AND o.payment_status = 'paid'
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_datetime_utc, $end_datetime_utc]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener items para cada orden
    $items_sql = "SELECT oi.*, p.id as product_id, p.stock_quantity FROM tuu_order_items oi LEFT JOIN products p ON oi.product_name = p.name WHERE oi.order_reference = ?";
    $items_stmt = $pdo->prepare($items_sql);
    
    // Convertir fechas UTC a hora de Chile y agregar items
    foreach ($transactions as &$transaction) {
        if (isset($transaction['created_at'])) {
            try {
                $utc_date = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
                $utc_date->setTimezone(new DateTimeZone('America/Santiago'));
                $transaction['created_at'] = $utc_date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Mantener fecha original si hay error
            }
        }
        
        // Obtener items del pedido
        $items_stmt->execute([$transaction['order_reference']]);
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
        $trans_stmt->execute([$transaction['order_reference']]);
        $all_inventory_transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener ingredientes con stock actual para cada item
        foreach ($items as $index => &$item) {
            if ($item['product_id']) {
                $recipeSql = "SELECT pr.quantity, pr.unit, i.name as ingredient_name, i.current_stock, i.id as ingredient_id 
                              FROM product_recipes pr 
                              INNER JOIN ingredients i ON pr.ingredient_id = i.id 
                              WHERE pr.product_id = ?";
                $recipeStmt = $pdo->prepare($recipeSql);
                $recipeStmt->execute([$item['product_id']]);
                $ingredients = $recipeStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($ingredients as &$ing) {
                    $ing['quantity_needed'] = $ing['quantity'];
                    // Buscar transacción de inventario para este ingrediente
                    foreach ($all_inventory_transactions as $trans) {
                        if ($trans['ingredient_id'] == $ing['ingredient_id']) {
                            $ing['previous_stock'] = $trans['previous_stock'];
                            $ing['new_stock'] = $trans['new_stock'];
                            break;
                        }
                    }
                }
                $item['ingredients'] = $ingredients;
            } else {
                $item['ingredients'] = [];
            }
            
            // Asignar inventory_transactions solo al PRIMER item
            if ($index === 0 && count($all_inventory_transactions) > 0) {
                $item['inventory_transactions'] = $all_inventory_transactions;
                $item['has_inventory_data'] = true;
            } else {
                $item['inventory_transactions'] = [];
                $item['has_inventory_data'] = false;
            }
        }
        unset($item);
        
        $transaction['items'] = $items;
        
        // Crear resumen de items
        if (count($items) > 0) {
            $items_summary = [];
            foreach ($items as $item) {
                $items_summary[] = $item['product_name'] . ' x' . $item['quantity'];
            }
            $transaction['items_summary'] = implode(', ', $items_summary);
            $transaction['items_count'] = count($items);
        } else {
            $transaction['items_summary'] = $transaction['product_name'] ?? '';
            $transaction['items_count'] = 0;
        }
    }
    unset($transaction);
    
    // Calcular estadísticas
    $total_revenue = 0;
    $total_transactions = count($transactions);
    
    $stats_by_method = [
        'cash' => 0,
        'card' => 0,
        'transfer' => 0,
        'webpay' => 0,
        'pedidosya' => 0
    ];
    
    foreach ($transactions as $t) {
        $amount = floatval($t['amount']);
        $total_revenue += $amount;
        $method = $t['payment_method'] ?? 'cash';
        if (isset($stats_by_method[$method])) {
            $stats_by_method[$method] += $amount;
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'combined_stats' => [
                'total_revenue' => $total_revenue,
                'total_transactions' => $total_transactions,
                'pos_revenue' => 0,
                'caja_revenue' => $stats_by_method['cash'] + $stats_by_method['card'] + $stats_by_method['transfer'],
                'app_revenue' => $stats_by_method['webpay'],
                'pedidosya_revenue' => $stats_by_method['pedidosya'],
                'online_revenue' => $total_revenue
            ],
            'all_transactions' => $transactions,
            'shift_info' => [
                'shift_date' => $shift_date,
                'start_chile' => $start_datetime_chile,
                'end_chile' => $end_datetime_chile,
                'start_utc' => $start_datetime_utc,
                'end_utc' => $end_datetime_utc
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
