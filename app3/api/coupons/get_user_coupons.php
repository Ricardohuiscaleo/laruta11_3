<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    
    $user_id = $_GET['user_id'] ?? null;
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'user_id requerido']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, coupon_type, status, stamps_used, created_at, used_at, order_id
        FROM user_coupons 
        WHERE user_id = ? 
        ORDER BY status ASC, created_at DESC
    ");
    $stmt->execute([$user_id]);
    $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $available = ['delivery_free' => 0, 'papas_bebida' => 0];
    $used = ['delivery_free' => 0, 'papas_bebida' => 0];
    
    foreach ($coupons as $coupon) {
        if ($coupon['status'] === 'available') {
            $available[$coupon['coupon_type']]++;
        } else {
            $used[$coupon['coupon_type']]++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'coupons' => $coupons,
        'available' => $available,
        'used' => $used,
        'has_delivery_free' => $available['delivery_free'] > 0,
        'has_papas_bebida' => $available['papas_bebida'] > 0
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
