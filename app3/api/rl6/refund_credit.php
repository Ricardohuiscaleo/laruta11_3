<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_number = $data['order_number'] ?? '';
$admin_id = $data['admin_id'] ?? 0;
$reason = $data['reason'] ?? 'Pedido cancelado';

if (empty($order_number)) {
    echo json_encode(['success' => false, 'message' => 'Número de orden requerido']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Obtener datos del pedido
    $stmt = $conn->prepare("
        SELECT user_id, monto_credito_rl6, pagado_con_credito_rl6 
        FROM tuu_orders 
        WHERE order_number = ? AND pagado_con_credito_rl6 = 1
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        throw new Exception('Pedido no encontrado o no pagado con crédito RL6');
    }

    $user_id = $order['user_id'];
    $monto = $order['monto_credito_rl6'];

    // 2. Reintegrar crédito (restar de credito_usado)
    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET credito_usado = credito_usado - ? 
        WHERE id = ? AND es_militar_rl6 = 1
    ");
    $stmt->bind_param("di", $monto, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No se pudo reintegrar el crédito');
    }

    // 3. Registrar transacción de reintegro
    $stmt = $conn->prepare("
        INSERT INTO rl6_credit_transactions 
        (user_id, order_id, amount, type, description) 
        VALUES (?, ?, ?, 'refund', ?)
    ");
    $stmt->bind_param("isds", $user_id, $order_number, $monto, $reason);
    $stmt->execute();

    // 4. Auditoría
    $stmt = $conn->prepare("
        INSERT INTO rl6_credit_audit 
        (user_id, admin_id, action, details) 
        VALUES (?, ?, 'refund', ?)
    ");
    $details = json_encode([
        'order_number' => $order_number,
        'amount' => $monto,
        'reason' => $reason
    ]);
    $stmt->bind_param("iis", $user_id, $admin_id, $details);
    $stmt->execute();

    // 5. Marcar pedido como reembolsado
    $stmt = $conn->prepare("
        UPDATE tuu_orders 
        SET pagado_con_credito_rl6 = 0, 
            monto_credito_rl6 = 0,
            order_status = 'cancelled'
        WHERE order_number = ?
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Crédito reintegrado exitosamente',
        'refunded_amount' => $monto
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
