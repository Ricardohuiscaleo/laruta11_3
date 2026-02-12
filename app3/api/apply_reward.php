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
    __DIR__ . '/../config.php',
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
    $reward_type = $data['reward_type'] ?? null;
    
    if (!$user_id || !$reward_type) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit;
    }
    
    $reward_stamps = [
        'delivery_free' => 2,
        'combo_free' => 4,
        'discount_10k' => 6
    ];
    
    if (!isset($reward_stamps[$reward_type])) {
        echo json_encode(['success' => false, 'error' => 'Recompensa invalida']);
        exit;
    }
    
    $stamps_required = $reward_stamps[$reward_type];
    
    // NUEVO SISTEMA: $10 = 1 punto, 1000 puntos = 1 sello
    $stmt = $pdo->prepare("
        SELECT 
            SUM(FLOOR((tuu_amount - COALESCE(delivery_fee, 0)) / 10)) as total_points,
            COALESCE(SUM(reward_stamps_consumed), 0) as consumed_stamps
        FROM tuu_orders 
        WHERE user_id = ? AND payment_status = 'paid'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $total_points = $result['total_points'] ?? 0;
    $total_stamps = floor($total_points / 1000);
    $available_stamps = $total_stamps - ($result['consumed_stamps'] ?? 0);
    
    if ($available_stamps < $stamps_required) {
        echo json_encode([
            'success' => false, 
            'error' => 'Sellos insuficientes',
            'available' => $available_stamps,
            'required' => $stamps_required
        ]);
        exit;
    }
    
    $discount_codes = [
        'delivery_free' => 'DELIVERY2',
        'combo_free' => 'COMBO4',
        'discount_10k' => 'DISCOUNT6'
    ];
    
    echo json_encode([
        'success' => true,
        'reward_type' => $reward_type,
        'stamps_consumed' => $stamps_required,
        'available_stamps' => $available_stamps - $stamps_required,
        'discount_code' => $discount_codes[$reward_type] ?? null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
