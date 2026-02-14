<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php',
    __DIR__ . '/../../../../../../config.php',
    __DIR__ . '/../../../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config not found']);
    exit;
}

try {
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "
        SELECT 
            u.id,
            u.nombre as name,
            u.email,
            u.telefono as phone,
            u.fecha_registro as registration_date,
            u.activo as is_active,
            u.direccion as city,
            COALESCE(o.order_count, 0) as total_orders,
            COALESCE(o.total_spent, 0) as total_spent,
            o.last_order_date,
            o.delivery_count,
            o.pickup_count,
            o.rewards_used,
            o.total_stamps_earned,
            o.stamps_consumed
        FROM usuarios u
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as order_count,
                SUM(product_price) as total_spent,
                MAX(created_at) as last_order_date,
                SUM(CASE WHEN delivery_type = 'delivery' THEN 1 ELSE 0 END) as delivery_count,
                SUM(CASE WHEN delivery_type = 'pickup' THEN 1 ELSE 0 END) as pickup_count,
                SUM(CASE WHEN reward_used IS NOT NULL THEN 1 ELSE 0 END) as rewards_used,
                FLOOR(SUM(product_price - COALESCE(delivery_fee, 0)) / 10000) as total_stamps_earned,
                COALESCE(SUM(reward_stamps_consumed), 0) as stamps_consumed
            FROM tuu_orders
            WHERE payment_status = 'paid'
            GROUP BY user_id
        ) o ON u.id = o.user_id
        ORDER BY u.id DESC
    ";

    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $users
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>