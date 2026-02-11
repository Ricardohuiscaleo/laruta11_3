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
    $coupon_id = $data['coupon_id'] ?? null;
    $order_id = $data['order_id'] ?? null;
    
    if (!$coupon_id || !$order_id) {
        echo json_encode(['success' => false, 'error' => 'coupon_id y order_id requeridos']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT status FROM user_coupons WHERE id = ?");
    $stmt->execute([$coupon_id]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        echo json_encode(['success' => false, 'error' => 'Cupón no encontrado']);
        exit;
    }
    
    if ($coupon['status'] === 'used') {
        echo json_encode(['success' => false, 'error' => 'Cupón ya fue usado']);
        exit;
    }
    
    $stmt = $pdo->prepare("
        UPDATE user_coupons 
        SET status = 'used', used_at = NOW(), order_id = ?
        WHERE id = ?
    ");
    $stmt->execute([$order_id, $coupon_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Cupón marcado como usado'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
