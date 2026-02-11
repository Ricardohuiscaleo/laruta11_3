<?php
header('Content-Type: application/json');

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
    
    $user_id = 4;
    
    // Test 1: Verificar que wallet existe
    $stmt = $pdo->prepare("SELECT * FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wallet = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test 2: Contar transacciones
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM wallet_transactions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Test 3: Obtener transacciones
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
        'transaction_count' => $count['count'],
        'transactions' => $transactions
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
