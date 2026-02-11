<?php
header('Content-Type: application/json');

function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../'];
    foreach ($levels as $level) {
        if (file_exists(__DIR__ . '/' . $level . 'config.php')) 
            return __DIR__ . '/' . $level . 'config.php';
    }
    return null;
}

$config = include findConfig();
$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Obtener última orden con combo
$stmt = $pdo->query("
    SELECT oi.*, o.order_number, o.payment_status
    FROM tuu_order_items oi
    JOIN tuu_orders o ON oi.order_id = o.id
    WHERE oi.item_type = 'combo'
    ORDER BY oi.id DESC
    LIMIT 1
");
$combo_order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$combo_order) {
    echo json_encode(['error' => 'No hay órdenes con combos']);
    exit;
}

$combo_data = json_decode($combo_order['combo_data'], true);

echo json_encode([
    'order_number' => $combo_order['order_number'],
    'payment_status' => $combo_order['payment_status'],
    'combo_name' => $combo_order['product_name'],
    'combo_data' => $combo_data,
    'analysis' => [
        'fixed_items_count' => count($combo_data['fixed_items'] ?? []),
        'selections_count' => count($combo_data['selections'] ?? []),
        'fixed_items' => array_map(function($item) {
            return [
                'product_id' => $item['product_id'],
                'name' => $item['product_name'],
                'quantity' => $item['quantity']
            ];
        }, $combo_data['fixed_items'] ?? []),
        'selections' => array_map(function($sel) {
            if (is_array($sel)) {
                return [
                    'id' => $sel['id'] ?? null,
                    'name' => $sel['name'] ?? null
                ];
            }
            return $sel;
        }, array_values($combo_data['selections'] ?? []))
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
