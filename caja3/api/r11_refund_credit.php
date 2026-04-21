<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$configPath = null;
$levels = ['', '../', '../../', '../../../', '../../../../'];
foreach ($levels as $level) {
    $path = __DIR__ . '/' . $level . 'config.php';
    if (file_exists($path)) { $configPath = $path; break; }
}

if (!$configPath) {
    echo json_encode(['success' => false, 'message' => 'config.php no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    $conn->set_charset("utf8mb4");
    if ($conn->connect_error) throw new Exception('Error de conexión: ' . $conn->connect_error);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_number = $data['order_number'] ?? '';
$reason = $data['reason'] ?? 'Pedido cancelado';

if (empty($order_number)) {
    echo json_encode(['success' => false, 'message' => 'Número de orden requerido']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Obtener datos del pedido
    $stmt = $conn->prepare("
        SELECT id, user_id, installment_amount, payment_method, order_status
        FROM tuu_orders 
        WHERE order_number = ? AND payment_method = 'r11_credit'
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) throw new Exception('Pedido no encontrado o no pagado con crédito R11');
    if ($order['order_status'] === 'cancelled') throw new Exception('Este pedido ya fue anulado');

    $user_id = $order['user_id'];
    $monto = $order['installment_amount'];

    // 2. Reintegrar crédito R11
    $stmt = $conn->prepare("UPDATE usuarios SET credito_r11_usado = GREATEST(0, credito_r11_usado - ?) WHERE id = ? AND es_credito_r11 = 1");
    $stmt->bind_param("di", $monto, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) throw new Exception('No se pudo reintegrar el crédito R11');

    // 3. Registrar transacción de reintegro
    $stmt = $conn->prepare("INSERT INTO r11_credit_transactions (user_id, order_id, amount, type, description) VALUES (?, ?, ?, 'refund', ?)");
    $stmt->bind_param("isds", $user_id, $order_number, $monto, $reason);
    $stmt->execute();

    // 4. Marcar pedido como cancelado
    $stmt = $conn->prepare("UPDATE tuu_orders SET order_status = 'cancelled', payment_status = 'unpaid' WHERE order_number = ?");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();

    // 5. Restaurar inventario (misma lógica que rl6_refund_credit.php)
    $restoredCount = 0;
    $inv_stmt = $conn->prepare("SELECT ingredient_id, product_id, quantity, unit, order_item_id FROM inventory_transactions WHERE order_reference = ? AND transaction_type = 'sale'");
    $inv_stmt->bind_param("s", $order_number);
    $inv_stmt->execute();
    $inv_result = $inv_stmt->get_result();

    while ($trans = $inv_result->fetch_assoc()) {
        $restore_qty = abs($trans['quantity']);

        if ($trans['ingredient_id']) {
            $conn->prepare("UPDATE ingredients SET current_stock = current_stock + ?, updated_at = NOW() WHERE id = ?")->execute([$restore_qty, $trans['ingredient_id']]);
            $restoredCount++;
        }
        if ($trans['product_id']) {
            $conn->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")->execute([$restore_qty, $trans['product_id']]);
            $restoredCount++;
        }
    }

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Crédito R11 reintegrado e inventario restaurado',
        'refunded_amount' => $monto,
        'inventory_restored' => $restoredCount
    ]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    $conn->close();
}
