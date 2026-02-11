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
    
    // Sin paginación - mostrar todas las transacciones
    $page = 1;
    $limit = 10000; // Límite alto para mostrar todas
    $offset = 0;
    
    // Fechas y filtros
    $start_date = $_GET['start_date'] ?? '2024-01-01';
    $end_date = $_GET['end_date'] ?? date('Y-m-d', strtotime('+1 day'));
    $sort_by = $_GET['sort_by'] ?? 'date'; // date, amount
    $sort_order = $_GET['sort_order'] ?? 'desc'; // asc, desc
    
    // Lógica de turnos para filtro de mes
    $filter_type = $_GET['filter_type'] ?? 'date_range';
    
    if ($filter_type === 'month') {
        $now = new DateTime('now', new DateTimeZone('America/Santiago'));
        $currentHour = (int)$now->format('G');
        
        $shiftToday = clone $now;
        if ($currentHour >= 0 && $currentHour < 4) {
            $shiftToday->modify('-1 day');
        }
        
        $currentYear = $shiftToday->format('Y');
        $currentMonth = $shiftToday->format('m');
        
        $firstShiftStart = "$currentYear-$currentMonth-01 17:00:00";
        $start_date_utc = date('Y-m-d H:i:s', strtotime($firstShiftStart . ' +3 hours'));
        
        $endOfMonth = new DateTime("$currentYear-$currentMonth-01");
        $endOfMonth->modify('last day of this month');
        $lastDay = $endOfMonth->format('Y-m-d');
        $dayAfter = date('Y-m-d', strtotime($lastDay . ' +1 day'));
        $lastShiftEnd = "$dayAfter 04:00:00";
        $end_date_utc = date('Y-m-d H:i:s', strtotime($lastShiftEnd . ' +3 hours'));
        
        $where_clause = "o.created_at >= ? AND o.created_at < ?";
        $params = [$start_date_utc, $end_date_utc];
    } else {
        $where_clause = "DATE(o.created_at) >= ? AND DATE(o.created_at) <= ?";
        $params = [$start_date, $end_date];
    }
    
    // NO usar tuu_pos_transactions - solo tuu_orders
    $pos_transactions = [];
    
    // Obtener transacciones de tuu_orders (APP y CAJA) con costos reales
    $online_sql = "
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
        WHERE $where_clause
        AND o.payment_status = 'paid'
        ORDER BY o.created_at DESC
    ";
    
    $stmt = $pdo->prepare($online_sql);
    $stmt->execute($params);
    $online_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Convertir fechas UTC a hora de Chile y aplicar lógica de turnos
    foreach ($online_transactions as &$transaction) {
        if (isset($transaction['created_at'])) {
            try {
                $utc_date = new DateTime($transaction['created_at'], new DateTimeZone('UTC'));
                $utc_date->setTimezone(new DateTimeZone('America/Santiago'));
                
                // Aplicar lógica de turnos: 00:00-03:59 pertenece al día anterior
                $hour = (int)$utc_date->format('G');
                if ($hour >= 0 && $hour < 4) {
                    $utc_date->modify('-1 day');
                }
                
                $transaction['created_at'] = $utc_date->format('Y-m-d H:i:s');
            } catch (Exception $e) {
                // Mantener fecha original si hay error
            }
        }
        
        // Obtener items del pedido con ingredientes
        $itemsSql = "SELECT oi.*, p.name as product_name_full, p.image_url, p.category_id, p.stock_quantity 
                     FROM tuu_order_items oi 
                     LEFT JOIN products p ON oi.product_id = p.id 
                     WHERE oi.order_id = ?";
        $itemsStmt = $pdo->prepare($itemsSql);
        $itemsStmt->execute([$transaction['id']]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
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
        
        // Asignar inventory_transactions solo al PRIMER item
        foreach ($items as $index => &$item) {
            if ($index === 0 && count($all_inventory_transactions) > 0) {
                $item['inventory_transactions'] = $all_inventory_transactions;
            } else {
                $item['inventory_transactions'] = [];
            }
        }
        unset($item);
        
        $transaction['items'] = $items;
    }
    unset($transaction);
    
    // Combinar y paginar
    $all_transactions = array_merge($pos_transactions, $online_transactions);
    
    // Ordenamiento dinámico
    usort($all_transactions, function($a, $b) use ($sort_by, $sort_order) {
        if ($sort_by === 'amount') {
            $val_a = floatval($a['amount']);
            $val_b = floatval($b['amount']);
        } else {
            $val_a = strtotime($a['created_at'] ?? $a['payment_date_time'] ?? '1970-01-01');
            $val_b = strtotime($b['created_at'] ?? $b['payment_date_time'] ?? '1970-01-01');
        }
        
        return $sort_order === 'asc' ? $val_a - $val_b : $val_b - $val_a;
    });
    
    $total_records = count($all_transactions);
    $total_pages = 1;
    $paginated_transactions = $all_transactions; // Mostrar todas sin paginar
    
    // Estadísticas
    $pos_total = array_sum(array_column($pos_transactions, 'amount'));
    $online_total = array_sum(array_column($online_transactions, 'amount'));
    
    // Separar por tipo basado en payment_method (solo pagadas)
    $app_transactions = array_filter($online_transactions, function($t) {
        return isset($t['payment_method']) && $t['payment_method'] === 'webpay';
    });
    $caja_transactions = array_filter($online_transactions, function($t) {
        return isset($t['payment_method']) && in_array($t['payment_method'], ['cash', 'card', 'transfer']);
    });
    $pedidosya_transactions = array_filter($online_transactions, function($t) {
        return isset($t['payment_method']) && $t['payment_method'] === 'pedidosya';
    });
    
    $app_total = array_sum(array_column($app_transactions, 'amount'));
    $caja_total = array_sum(array_column($caja_transactions, 'amount'));
    $pedidosya_total = array_sum(array_column($pedidosya_transactions, 'amount'));
    
    $combined_stats = [
        'pos_revenue' => $pos_total,
        'caja_revenue' => $caja_total,
        'app_revenue' => $app_total,
        'pedidosya_revenue' => $pedidosya_total,
        'online_revenue' => $online_total,
        'total_revenue' => $pos_total + $online_total,
        'pos_transactions' => count($pos_transactions),
        'caja_transactions' => count($caja_transactions),
        'app_transactions' => count($app_transactions),
        'pedidosya_transactions' => count($pedidosya_transactions),
        'online_transactions' => count($online_transactions),
        'total_transactions' => $total_records,
        'date_range' => [
            'start_date' => $start_date,
            'end_date' => $end_date
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'combined_stats' => $combined_stats,
            'all_transactions' => $paginated_transactions,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_records' => $total_records,
                'per_page' => $limit
            ],
            'debug' => [
                'pos_found' => count($pos_transactions),
                'caja_found' => count($caja_transactions),
                'app_found' => count($app_transactions),
                'pedidosya_found' => count($pedidosya_transactions),
                'total_online' => count($online_transactions),
                'date_range' => [$start_date, $end_date]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>