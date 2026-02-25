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
                dispatch_photo_url,
                DATE_FORMAT(DATE_SUB(created_at, INTERVAL 3 HOUR), '%d-%m-%Y %H:%i') as hora_chile,
                DATE_FORMAT(DATE_SUB(created_at, INTERVAL 3 HOUR), '%H:%i') as hora_corta,
                created_at
            FROM tuu_orders
            WHERE created_at >= ? AND created_at < ?
            AND payment_status = 'paid'
            AND order_number NOT LIKE 'RL6-%'
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

        // Obtener transacciones agrupadas por order_item_id
        $transSql = "
            SELECT 
                it.order_item_id,
                it.quantity,
                it.ingredient_id,
                it.product_id,
                COALESCE(i.name, p.name) as ingredient_name,
                COALESCE(it.unit, i.unit, 'unidad') as unit,
                i.current_stock as ing_stock,
                p.stock_quantity as prod_stock
            FROM inventory_transactions it
            LEFT JOIN ingredients i ON it.ingredient_id = i.id
            LEFT JOIN products p ON it.product_id = p.id
            WHERE it.order_reference = ?
            ORDER BY it.order_item_id ASC, it.id ASC
        ";
        $transStmt = $pdo->prepare($transSql);
        $transStmt->execute([$order['order_number']]);
        $transactions = $transStmt->fetchAll(PDO::FETCH_ASSOC);

        // Agrupar transacciones por order_item_id
        $trans_by_item = [];
        $trans_unknown = [];
        foreach ($transactions as $trans) {
            $item_id = $trans['order_item_id'];
            if ($item_id !== null) {
                if (!isset($trans_by_item[$item_id]))
                    $trans_by_item[$item_id] = [];
                $trans_by_item[$item_id][] = $trans;
            }
            else {
                $trans_unknown[] = $trans;
            }
        }

        // Asignar ingredientes a cada item
        foreach ($order['items'] as &$item) {
            $item_trans = $trans_by_item[$item['id']] ?? [];
            $ing_map = [];
            foreach ($item_trans as $trans) {
                $qtyUsed = abs(floatval($trans['quantity']));
                $unit = $trans['unit'];
                if ($unit === 'g' && $qtyUsed < 1)
                    $qtyUsed *= 1000;
                elseif ($unit === 'kg') {
                    $qtyUsed *= 1000;
                    $unit = 'g';
                }
                $k = $trans['ingredient_name'];
                if (!isset($ing_map[$k]))
                    $ing_map[$k] = ['ingredient_name' => $k, 'quantity_needed' => 0, 'unit' => $unit];
                $ing_map[$k]['quantity_needed'] += $qtyUsed;
            }
            $item['ingredients'] = array_values($ing_map);
        }
        unset($item);

        // Transacciones sin order_item_id
        $order_ing_map = [];
        foreach ($trans_unknown as $trans) {
            $qtyUsed = abs(floatval($trans['quantity']));
            $unit = $trans['unit'];
            if ($unit === 'g' && $qtyUsed < 1)
                $qtyUsed *= 1000;
            elseif ($unit === 'kg') {
                $qtyUsed *= 1000;
                $unit = 'g';
            }
            $k = $trans['ingredient_name'];
            if (!isset($order_ing_map[$k]))
                $order_ing_map[$k] = ['ingredient_name' => $k, 'quantity_needed' => 0, 'unit' => $unit];
            $order_ing_map[$k]['quantity_needed'] += $qtyUsed;
        }
        $order['order_ingredients'] = array_values($order_ing_map);

        // Acumular consumo global con stock y tipo para indicadores
        foreach ($transactions as $trans) {
            $qtyUsed = abs(floatval($trans['quantity']));
            $unit = $trans['unit'];
            if ($unit === 'g' && $qtyUsed < 1)
                $qtyUsed *= 1000;
            elseif ($unit === 'kg') {
                $qtyUsed *= 1000;
                $unit = 'g';
            }
            $ingKey = $trans['ingredient_name'];

            if (!isset($ingredient_consumption[$ingKey])) {
                $stock = $trans['ingredient_id'] ? $trans['ing_stock'] : $trans['prod_stock'];
                $ingredient_consumption[$ingKey] = [
                    'name' => $ingKey,
                    'total' => 0,
                    'unit' => $unit,
                    'stock_actual' => $stock,
                    'ingredient_id' => $trans['ingredient_id'],
                    'product_id' => $trans['product_id']
                ];
            }
            $ingredient_consumption[$ingKey]['total'] += $qtyUsed;
        }
    }
    unset($order);

    // Optimización: Calcular Max Consumo Diario en un solo batch (últimos 30 días)
    $ing_ids = array_filter(array_column($ingredient_consumption, 'ingredient_id'));
    $prod_ids = array_filter(array_column($ingredient_consumption, 'product_id'));

    $max_data_map = [];

    if (!empty($ing_ids) || !empty($prod_ids)) {
        $where_clauses = [];
        $params = [];

        if (!empty($ing_ids)) {
            $where_clauses[] = "ingredient_id IN (" . implode(',', array_fill(0, count($ing_ids), '?')) . ")";
            $params = array_merge($params, array_values($ing_ids));
        }
        if (!empty($prod_ids)) {
            $where_clauses[] = "product_id IN (" . implode(',', array_fill(0, count($prod_ids), '?')) . ")";
            $params = array_merge($params, array_values($prod_ids));
        }

        $where_sql = implode(' OR ', $where_clauses);

        $batchMaxSql = "
            SELECT ingredient_id, product_id, AVG(daily_total) as avg_daily
            FROM (
                SELECT ingredient_id, product_id, DATE(created_at) as day, SUM(ABS(quantity)) as daily_total
                FROM inventory_transactions
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND transaction_type = 'sale'
                AND ($where_sql)
                GROUP BY ingredient_id, product_id, DATE(created_at)
            ) as daily_usage
            GROUP BY ingredient_id, product_id
        ";

        $batchStmt = $pdo->prepare($batchMaxSql);
        $batchStmt->execute($params);
        while ($row = $batchStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['ingredient_id'] ? "ing_" . $row['ingredient_id'] : "prod_" . $row['product_id'];
            $max_data_map[$key] = floatval($row['avg_daily']);
        }
    }

    // Asignar Max Consumo a cada item de la lista y normalizar unidades
    foreach ($ingredient_consumption as $ingKey => &$ing) {
        $key = $ing['ingredient_id'] ? "ing_" . $ing['ingredient_id'] : "prod_" . $ing['product_id'];
        $max_val = $max_data_map[$key] ?? 0;

        // Normalizar Stock y Max Consumo si la unidad es gramos
        if ($ing['unit'] === 'g') {
            // Si el stock viene en kilos (valor pequeño) pero la unidad es gramos, convertir
            // OJO: Asumimos que si stock < 50 y unidad es 'g', probablemente esté en kilos en la DB
            // Pero una forma más segura es ver si el valor de stock en DB vs transacciones difiere en escala
            if ($ing['stock_actual'] < 100 && $ing['stock_actual'] > 0) {
                $ing['stock_actual'] = $ing['stock_actual'] * 1000;
            }
            if ($max_val < 100 && $max_val > 0) {
                $max_val = $max_val * 1000;
            }
        }

        $ing['max_daily_consumption'] = $max_val;
    }
    unset($ing);

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
        }
        else if ($ing['unit'] === 'kg') {
            $ing['total'] = $ing['total'] * 1000;
            $ing['unit'] = 'g';
        }
    }

    uasort($ingredient_consumption, function ($a, $b) {
        $stockA = floatval($a['stock_actual']);
        $maxA = floatval($a['max_daily_consumption']);
        $stockB = floatval($b['stock_actual']);
        $maxB = floatval($b['max_daily_consumption']);

        // Calcular nivel de criticidad (2: Rojo, 1: Amarillo, 0: Verde)
        if ($maxA > 0) {
            $critA = ($stockA < $maxA) ? 2 : (($stockA < $maxA * 3) ? 1 : 0);
        }
        else {
            $critA = ($stockA < 0) ? 2 : 0;
        }

        if ($maxB > 0) {
            $critB = ($stockB < $maxB) ? 2 : (($stockB < $maxB * 3) ? 1 : 0);
        }
        else {
            $critB = ($stockB < 0) ? 2 : 0;
        }

        if ($critA !== $critB) {
            return $critB <=> $critA;
        }

        // Si tienen la misma criticidad, ordenar por nombre
        return strcmp($a['name'], $b['name']);
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

}
catch (Exception $e) {
    error_log("Get Sales Detail Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>