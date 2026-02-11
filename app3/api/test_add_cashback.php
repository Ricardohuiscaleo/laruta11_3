<?php
// Script temporal para testing de cashback
// ELIMINAR EN PRODUCCIÓN

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/config.php';

// Obtener user_id del query string
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 6000;

if ($user_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

try {
    // Actualizar wallet
    $stmt = $pdo->prepare("
        UPDATE user_wallet 
        SET balance = balance + ?,
            total_earned = total_earned + ?,
            updated_at = NOW()
        WHERE user_id = ?
    ");
    $stmt->execute([$amount, $amount, $user_id]);
    
    // Registrar transacción
    $stmt = $pdo->prepare("
        INSERT INTO wallet_transactions (user_id, type, amount, description, created_at)
        VALUES (?, 'earned', ?, 'Testing cashback', NOW())
    ");
    $stmt->execute([$user_id, $amount]);
    
    echo json_encode([
        'success' => true,
        'message' => "Cashback de $$amount agregado al usuario $user_id"
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
