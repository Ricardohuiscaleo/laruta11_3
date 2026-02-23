<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
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
    
    $start_date = $_GET['start_date'] ?? date('Y-m-d 00:00:00');
    $end_date = $_GET['end_date'] ?? date('Y-m-d 23:59:59');
    
    $sql = "SELECT 
                id,
                order_number,
                customer_name,
                customer_phone,
                payment_method,
                payment_status,
                order_status,
                installment_amount,
                discount_amount,
                cashback_used,
                delivery_type,
                delivery_address,
                pickup_time,
                customer_notes,
                delivery_extras_items,
                DATE_FORMAT(DATE_SUB(created_at, INTERVAL 3 HOUR), '%d-%m-%Y %H:%i') as hora_chile,
                DATE_FORMAT(DATE_SUB(created_at, INTERVAL 3 HOUR), '%H:%i') as hora_corta,
                created_at
            FROM tuu_orders
            WHERE created_at >= ? AND created_at < ?
            AND payment_status = 'paid'
            AND (order_status IS NULL OR order_status NOT IN ('cancelled', 'failed'))
            ORDER BY created_at DESC
            LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$start_date, $end_date]);
    $all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("SALES_DETAIL - Query params: start=$start_date, end=$end_date");
    error_log("SALES_DETAIL - Found " . count($all_orders) . " orders");
    error_log("SALES_DETAIL - Order IDs: " . implode(', ', array_column($all_orders, 'id')));
    
    // Eliminar duplicados por ID
    $orders = [];
    $seen_ids = [];
    foreach ($all_orders as $order) {
        if (!in_array($order['id'], $seen_ids)) {
            $orders[] = $order;
            $seen_ids[] = $order['id'];
        }
    }
    
    // Obtener items de cada pedido con transacciones de inventario REALES
    $ingredient_consumption = [];
    
    foreach ($orders as $key => &$order) {
        $itemsSql = "SELECT id, product_name, quantity, product_price, item_type, combo_data, product_id 
                     FROM tuu_order_items 
                     WHERE order_id = ?";
        $itemsStmt = $pdo->prepare($itemsSql);
        $itemsStmt->execute([$order['id']]);
        $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Obtener TODAS las transacciones de la orden por order_reference (no por item)
        $transSql = "
            SELECT 
                it.quantity,
                COALESCE(i.name, p.name) as ingredient_name,
                COALESCE(it.unit, i.unit, 'unidad') as unit
            FROM inventory_transactions it
            LEFT JOIN ingredients i ON it.ingredient_id = i.id
            LEFT JOIN products p ON it.product_id = p.id
            WHERE it.order_reference = ?
            ORDER BY it.id ASC
        ";
        $transStmt = $pdo->prepare($transSql);
        $transStmt->execute([$order['order_number']]);
        $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Asignar todas las transacciones al primer item, resto vacío
        foreach ($order['items'] as $idx => &$item) {
            $item['ingredients'] = [];
            if ($idx === 0) {
                foreach ($transactions as $trans) {
                    $qtyUsed = abs(floatval($trans['quantity']));
                    $unit = $trans['unit'];
                    if ($unit === 'g' && $qtyUsed < 1) {
                        $qtyUsed = $qtyUsed * 1000;
                    } else if ($unit === 'kg') {
                        $qtyUsed = $qtyUsed * 1000;
                        $unit = 'g';
                    }
                    $item['ingredients'][] = [
                        'ingredient_name' => $trans['ingredient_name'],
                        'quantity_needed' => $qtyUsed,
                        'unit' => $unit
                    ];
                    $ingKey = $trans['ingredient_name'];
                    if (!isset($ingredient_consumption[$ingKey])) {
                        $ingredient_consumption[$ingKey] = ['name' => $trans['ingredient_name'], 'total' => 0, 'unit' => $unit];
                    }
                    $ingredient_consumption[$ingKey]['total'] += $qtyUsed;
                }
            }
        }
    }
    unset($order); // Liberar referencia
    
    // Calcular estadísticas
    $total_sales = array_sum(array_column($orders, 'installment_amount'));
    $total_discounts = array_sum(array_column($orders, 'discount_amount'));
    $total_cashback = array_sum(array_column($orders, 'cashback_used'));
    
    $payment_methods = [];
    $delivery_types = ['delivery' => 0, 'pickup' => 0];
    foreach ($orders as $order) {
        $method = $order['payment_method'];
        $payment_methods[$method] = ($payment_methods[$method] ?? 0) + floatval($order['installment_amount']);
        
        $type = $order['delivery_type'] ?? 'pickup';
        $delivery_types[$type] = ($delivery_types[$type] ?? 0) + 1;
    }
    
    // Convertir kg a g en consumo total y ordenar
    foreach ($ingredient_consumption as &$ing) {
        if ($ing['unit'] === 'g' && $ing['total'] < 1) {
            $ing['total'] = $ing['total'] * 1000;
        } else if ($ing['unit'] === 'kg') {
            $ing['total'] = $ing['total'] * 1000;
            $ing['unit'] = 'g';
        }
    }
    
    uasort($ingredient_consumption, function($a, $b) {
        return $b['total'] <=> $a['total'];
    });
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total_orders' => count($orders),
        'stats' => [
            'total_sales' => $total_sales,
            'total_discounts' => $total_discounts,
            'total_cashback' => $total_cashback,
            'avg_ticket' => count($orders) > 0 ? $total_sales / count($orders) : 0,
            'payment_methods' => $payment_methods,
            'delivery_types' => $delivery_types,
            'ingredient_consumption' => array_values($ingredient_consumption)
        ],
        'period' => [
            'start' => $start_date,
            'end' => $end_date
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Get Sales Detail Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
