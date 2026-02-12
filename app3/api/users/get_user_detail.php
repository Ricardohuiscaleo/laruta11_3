<?php
// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'User ID required']);
    exit;
}

try {
    // Usar base de datos app
    $pdo = new PDO("mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4", $config['app_db_user'], $config['app_db_pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $user_id = (int)$_GET['id'];

    // Obtener datos del usuario
    $stmt = $pdo->prepare("SELECT * FROM app_users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Obtener pedidos del usuario
    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            GROUP_CONCAT(
                CONCAT(oi.quantity, 'x ', oi.product_name, ' ($', oi.unit_price, ')')
                SEPARATOR ', '
            ) as items_summary
        FROM user_orders o
        LEFT JOIN user_order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.order_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Estadísticas del usuario
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COALESCE(SUM(total_amount), 0) as total_spent,
            COALESCE(AVG(total_amount), 0) as avg_order_value,
            MAX(order_date) as last_order_date,
            MIN(order_date) as first_order_date
        FROM user_orders 
        WHERE user_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Productos más comprados
    $stmt = $pdo->prepare("
        SELECT 
            oi.product_name,
            SUM(oi.quantity) as total_quantity,
            COUNT(DISTINCT o.id) as order_count,
            SUM(oi.total_price) as total_spent
        FROM user_order_items oi
        JOIN user_orders o ON oi.order_id = o.id
        WHERE o.user_id = ? AND o.status != 'cancelled'
        GROUP BY oi.product_name
        ORDER BY total_quantity DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $favorite_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'user' => $user,
            'orders' => $orders,
            'stats' => $stats,
            'favorite_products' => $favorite_products
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>