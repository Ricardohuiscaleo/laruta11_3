<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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
    $input = json_decode(file_get_contents('php://input'), true);
    $session_id = $input['session_id'] ?? null;
    $closed_by = $input['closed_by'] ?? 'Cajero';
    $closing_notes = $input['closing_notes'] ?? null;
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Si no se proporciona session_id, buscar la sesión abierta de hoy
    if (!$session_id) {
        $today = date('Y-m-d');
        $find_sql = "SELECT id, opened_at FROM cash_register_sessions WHERE session_date = ? AND status = 'open' LIMIT 1";
        $find_stmt = $pdo->prepare($find_sql);
        $find_stmt->execute([$today]);
        $session = $find_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode([
                'success' => false,
                'error' => 'No hay sesión de caja abierta para cerrar'
            ]);
            exit;
        }
        
        $session_id = $session['id'];
        $opened_at = $session['opened_at'];
    } else {
        // Obtener opened_at de la sesión
        $get_sql = "SELECT opened_at FROM cash_register_sessions WHERE id = ?";
        $get_stmt = $pdo->prepare($get_sql);
        $get_stmt->execute([$session_id]);
        $session = $get_stmt->fetch(PDO::FETCH_ASSOC);
        $opened_at = $session['opened_at'];
    }
    
    // Obtener resumen de ventas desde que se abrió la caja
    $summary_sql = "SELECT 
                        payment_method,
                        COUNT(*) as count,
                        SUM(installment_amount) as total
                    FROM tuu_orders
                    WHERE created_at >= ?
                    AND payment_status = 'paid'
                    AND order_status != 'cancelled'
                    GROUP BY payment_method";
    
    $summary_stmt = $pdo->prepare($summary_sql);
    $summary_stmt->execute([$opened_at]);
    $summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar resumen
    $totals = [
        'cash' => ['count' => 0, 'total' => 0],
        'card' => ['count' => 0, 'total' => 0],
        'transfer' => ['count' => 0, 'total' => 0],
        'pedidosya' => ['count' => 0, 'total' => 0],
        'webpay' => ['count' => 0, 'total' => 0]
    ];
    
    foreach ($summary as $row) {
        $method = $row['payment_method'];
        if (isset($totals[$method])) {
            $totals[$method] = [
                'count' => (int)$row['count'],
                'total' => (float)$row['total']
            ];
        }
    }
    
    $total_amount = array_sum(array_column($totals, 'total'));
    $total_orders = array_sum(array_column($totals, 'count'));
    
    // Actualizar sesión con totales y cerrar
    $update_sql = "UPDATE cash_register_sessions SET
                    closed_at = NOW(),
                    closed_by = ?,
                    closing_notes = ?,
                    cash_total = ?,
                    cash_count = ?,
                    card_total = ?,
                    card_count = ?,
                    transfer_total = ?,
                    transfer_count = ?,
                    pedidosya_total = ?,
                    pedidosya_count = ?,
                    webpay_total = ?,
                    webpay_count = ?,
                    total_amount = ?,
                    total_orders = ?,
                    status = 'closed'
                   WHERE id = ?";
    
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([
        $closed_by,
        $closing_notes,
        $totals['cash']['total'],
        $totals['cash']['count'],
        $totals['card']['total'],
        $totals['card']['count'],
        $totals['transfer']['total'],
        $totals['transfer']['count'],
        $totals['pedidosya']['total'],
        $totals['pedidosya']['count'],
        $totals['webpay']['total'],
        $totals['webpay']['count'],
        $total_amount,
        $total_orders,
        $session_id
    ]);
    
    echo json_encode([
        'success' => true,
        'session_id' => $session_id,
        'totals' => $totals,
        'total_amount' => $total_amount,
        'total_orders' => $total_orders,
        'message' => 'Caja cerrada exitosamente'
    ]);
    
} catch (Exception $e) {
    error_log("Close Cash Register Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
