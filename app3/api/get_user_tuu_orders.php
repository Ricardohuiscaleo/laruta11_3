<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

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
    
    $data = json_decode(file_get_contents('php://input'), true);
    $user_id = $data['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'user_id requerido']);
        exit;
    }
    
    // Obtener pedidos pagados de tuu_orders (monto sin delivery)
    $stmt = $pdo->prepare("
        SELECT 
            order_number,
            (tuu_amount - COALESCE(delivery_fee, 0)) as amount,
            payment_status,
            created_at,
            delivery_type,
            delivery_address,
            reward_used,
            reward_stamps_consumed
        FROM tuu_orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // SISTEMA: $10 = 1 punto, 1.000 puntos = 1 sello (1% cashback)
    $total_points = 0;
    $total_stamps = 0;
    $consumed_stamps = 0;
    
    foreach ($orders as $order) {
        if ($order['payment_status'] === 'paid') {
            $amount = floatval($order['amount']);
            $points = floor($amount / 10); // $10 = 1 punto
            $total_points += $points;
            $total_stamps += floor($points / 1000); // 1.000 puntos = 1 sello
            $consumed_stamps += intval($order['reward_stamps_consumed'] ?? 0);
        }
    }
    
    $available_stamps = $total_stamps - $consumed_stamps;
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total_points' => $total_points,
        'total_stamps' => $total_stamps,
        'consumed_stamps' => $consumed_stamps,
        'available_stamps' => $available_stamps
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
