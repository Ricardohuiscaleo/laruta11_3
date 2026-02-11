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
    $start_date = $_GET['start_date'] ?? '2025-08-01';
    $end_date = $_GET['end_date'] ?? '2025-09-30';
    $sort_by = $_GET['sort_by'] ?? 'date'; // date, amount
    $sort_order = $_GET['sort_order'] ?? 'desc'; // asc, desc
    
    // Obtener transacciones POS
    $pos_sql = "
        SELECT 
            sale_id,
            amount,
            status,
            pos_serial_number,
            transaction_type,
            payment_date_time,
            items_json,
            'pos' as payment_source
        FROM tuu_pos_transactions 
        WHERE DATE(payment_date_time) >= ? AND DATE(payment_date_time) <= ?
            AND status != 'pending'
        ORDER BY payment_date_time DESC
    ";
    
    $stmt = $pdo->prepare($pos_sql);
    $stmt->execute([$start_date, $end_date]);
    $pos_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener transacciones online (órdenes APP)
    $online_sql = "
        SELECT 
            order_number as order_reference,
            installment_amount as amount,
            status,
            customer_name,
            customer_phone,
            product_name,
            created_at,
            'app' as payment_source
        FROM tuu_orders 
        WHERE installment_amount IS NOT NULL 
            AND DATE(created_at) >= ? AND DATE(created_at) <= ?
            AND status != 'pending'
        ORDER BY created_at DESC
    ";
    
    $stmt = $pdo->prepare($online_sql);
    $stmt->execute([$start_date, $end_date]);
    $online_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    $combined_stats = [
        'pos_revenue' => $pos_total,
        'online_revenue' => $online_total,
        'total_revenue' => $pos_total + $online_total,
        'pos_transactions' => count($pos_transactions),
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
                'app_found' => count($online_transactions),
                'date_range' => [$start_date, $end_date]
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>