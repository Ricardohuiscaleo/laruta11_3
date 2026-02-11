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
    $amount = $data['amount'] ?? null;
    
    if (!$user_id || !$amount) {
        echo json_encode(['success' => false, 'error' => 'user_id y amount requeridos']);
        exit;
    }
    
    // Calcular 1% de cashback
    $cashback = round($amount * 0.01);
    
    if ($cashback <= 0) {
        echo json_encode(['success' => true, 'cashback_generated' => 0, 'message' => 'Monto muy bajo para generar cashback']);
        exit;
    }
    
    // Actualizar wallet
    $stmt = $pdo->prepare("
        UPDATE user_wallet 
        SET balance = balance + ?,
            total_earned = total_earned + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$cashback, $cashback, $user_id]);
    
    // Obtener nuevo balance
    $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();
    
    // Registrar transacción
    $stmt = $pdo->prepare("
        INSERT INTO wallet_transactions 
        (user_id, type, amount, description, balance_after)
        VALUES (?, 'earned', ?, ?, ?)
    ");
    $stmt->execute([$user_id, $cashback, 'Cashback 1% - Compra', $new_balance]);
    
    echo json_encode([
        'success' => true,
        'cashback_generated' => $cashback,
        'new_balance' => $new_balance
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
