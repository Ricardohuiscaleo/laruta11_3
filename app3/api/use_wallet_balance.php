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
    $amount = $data['amount'] ?? 0;
    $order_id = $data['order_id'] ?? null;
    
    if (!$user_id || $amount < 0) {
        echo json_encode(['success' => false, 'error' => 'Datos invalidos']);
        exit;
    }
    
    // Si amount es 0, permitir (no usar cashback)
    if ($amount == 0) {
        echo json_encode(['success' => true, 'new_balance' => 0, 'amount_used' => 0]);
        exit;
    }
    
    // Verificar saldo disponible
    $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $current_balance = $stmt->fetchColumn();
    
    // Validar mínimo de $500 para usar cashback
    if ($current_balance < 500) {
        echo json_encode([
            'success' => false, 
            'error' => 'Cashback disponible desde $500',
            'available' => $current_balance
        ]);
        exit;
    }
    
    // Validar que sea múltiplo de $10
    if ($amount % 10 != 0) {
        echo json_encode([
            'success' => false, 
            'error' => 'El monto debe ser múltiplo de $10'
        ]);
        exit;
    }
    
    if ($current_balance < $amount) {
        echo json_encode([
            'success' => false, 
            'error' => 'Saldo insuficiente',
            'available' => $current_balance
        ]);
        exit;
    }
    
    // Descontar del wallet
    $stmt = $pdo->prepare("
        UPDATE user_wallet 
        SET balance = balance - ?,
            total_used = total_used + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$amount, $amount, $user_id]);
    
    // Obtener nuevo balance
    $stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();
    
    // Registrar transacción
    $description = $order_id ? "Usado en pedido $order_id" : "Usado en compra";
    $stmt = $pdo->prepare("
        INSERT INTO wallet_transactions 
        (user_id, type, amount, order_id, description, balance_before, balance_after)
        VALUES (?, 'used', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $amount, $order_id, $description, $current_balance, $new_balance]);
    
    echo json_encode([
        'success' => true,
        'new_balance' => $new_balance,
        'amount_used' => $amount
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
