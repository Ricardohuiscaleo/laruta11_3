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
    $coupon_type = $data['coupon_type'] ?? null;
    
    if (!$user_id || !$coupon_type) {
        echo json_encode(['success' => false, 'error' => 'user_id y coupon_type requeridos']);
        exit;
    }
    
    if (!in_array($coupon_type, ['delivery_free', 'papas_bebida'])) {
        echo json_encode(['success' => false, 'error' => 'Tipo de cupón inválido']);
        exit;
    }
    
    $stamps_required = $coupon_type === 'delivery_free' ? 2 : 4;
    
    $stmt = $pdo->prepare("
        SELECT FLOOR(SUM(FLOOR((installment_amount - COALESCE(delivery_fee, 0)) / 10)) / 1000) as total_stamps
        FROM tuu_orders WHERE user_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$user_id]);
    $total_stamps = $stmt->fetchColumn() ?? 0;
    
    $current_level_stamps = $total_stamps % 6;
    
    if ($current_level_stamps < $stamps_required) {
        echo json_encode([
            'success' => false, 
            'error' => "Necesitas $stamps_required sellos. Tienes $current_level_stamps."
        ]);
        exit;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO user_coupons (user_id, coupon_type, status, stamps_used)
        VALUES (?, ?, 'available', ?)
    ");
    $stmt->execute([$user_id, $coupon_type, $stamps_required]);
    
    $names = ['delivery_free' => 'Delivery Gratis', 'papas_bebida' => 'Papas + Bebida Gratis'];
    
    echo json_encode([
        'success' => true,
        'coupon_id' => $pdo->lastInsertId(),
        'message' => "¡Cupón de {$names[$coupon_type]} activado!"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
