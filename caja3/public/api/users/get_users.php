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
            COALESCE(o.total_spent, 0) as total_spent
        FROM usuarios u
        LEFT JOIN (
            SELECT 
                user_id,
                COUNT(*) as order_count,
                SUM(product_price) as total_spent
            FROM tuu_orders
            WHERE payment_status = 'paid'
            GROUP BY user_id
        ) o ON u.id = o.user_id
        ORDER BY u.fecha_registro DESC
        LIMIT 50
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