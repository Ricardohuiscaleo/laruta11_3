<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'ID de pedido requerido']);
        exit;
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->beginTransaction();

    // 1. Obtener información del pedido antes de anular
    $orderSql = "SELECT order_number, payment_method, payment_status, installment_amount FROM tuu_orders WHERE id = ?";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pedido no encontrado');
    }

    // 2. Actualizar el estado del pedido a 'cancelled'
    $sql = "UPDATE tuu_orders SET order_status = 'cancelled', updated_at = NOW() WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);

    // 3. Restaurar crédito R11 si fue pagado con crédito trabajador
    if ($order['payment_method'] === 'r11_credit') {
        $amount = floatval($order['installment_amount']);
        $userSql = "SELECT user_id FROM tuu_orders WHERE id = ?";
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute([$orderId]);
        $userId = $userStmt->fetchColumn();
        
        if ($userId) {
            // Restaurar crédito usado
            $pdo->prepare("UPDATE usuarios SET credito_r11_usado = GREATEST(0, credito_r11_usado - ?) WHERE id = ?")->execute([$amount, $userId]);
            // Registrar refund en transacciones
            $pdo->prepare("INSERT INTO r11_credit_transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'refund', ?, ?)")
                ->execute([$userId, $amount, "Anulación orden #{$order['order_number']}", $order['order_number']]);
        }
    }

    // 4. Restaurar crédito RL6 si fue pagado con crédito militar
    if ($order['payment_method'] === 'rl6_credit') {
        $amount = floatval($order['installment_amount']);
        $userSql = "SELECT user_id FROM tuu_orders WHERE id = ?";
        $userStmt = $pdo->prepare($userSql);
        $userStmt->execute([$orderId]);
        $userId = $userStmt->fetchColumn();
        
        if ($userId) {
            $pdo->prepare("UPDATE usuarios SET credito_usado = GREATEST(0, credito_usado - ?) WHERE id = ?")->execute([$amount, $userId]);
            $pdo->prepare("INSERT INTO rl6_credit_transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'refund', ?, ?)")
                ->execute([$userId, $amount, "Anulación orden #{$order['order_number']}", $order['order_number']]);
        }
    }

    // 5. Si era transferencia pagada, registrar reembolso pendiente
    if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'paid') {
        $refundSql = "INSERT INTO refunds (order_id, order_number, amount, status, created_at) 
                      VALUES (?, ?, ?, 'pending', NOW())";
        $refundStmt = $pdo->prepare($refundSql);
        $refundStmt->execute([$orderId, $order['order_number'], $order['installment_amount']]);
    }

    // 4. TODO: Devolver inventario (ingredientes/productos)
    // Aquí iría la lógica para devolver stock al inventario
    
    $pdo->commit();

    $message = 'Pedido anulado exitosamente';
    if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'paid') {
        $message .= '. Reembolso registrado como pendiente.';
    }

    echo json_encode(['success' => true, 'message' => $message]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>