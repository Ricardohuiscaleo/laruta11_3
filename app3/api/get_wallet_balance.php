<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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
    
    // Obtener o crear wallet
    $stmt = $pdo->prepare("
        INSERT INTO user_wallet (user_id, balance, total_earned, total_used)
        VALUES (?, 0, 0, 0)
        ON DUPLICATE KEY UPDATE user_id = user_id
    ");
    $stmt->execute([$user_id]);
    
    // Obtener saldo
    $stmt = $pdo->prepare("
        SELECT balance, total_earned, total_used, updated_at
        FROM user_wallet
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener últimas transacciones
    $stmt = $pdo->prepare("
        SELECT type, amount, order_id, description, balance_after, created_at
        FROM wallet_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'wallet' => $wallet,
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
