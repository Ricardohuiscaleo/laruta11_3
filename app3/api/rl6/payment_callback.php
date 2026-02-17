<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

try {
    $order_id = $_GET['x_reference'] ?? null;
    $result = $_GET['x_result'] ?? null;
    $transaction_id = $_GET['x_transaction_id'] ?? null;
    $amount = $_GET['x_amount'] ?? null;

    if (!$order_id || !str_starts_with($order_id, 'RL6-')) {
        throw new Exception('Order ID RL6 requerido');
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Determinar estados
    $new_status = match($result) {
        'completed' => 'completed',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        default => 'pending'
    };

    $payment_status = ($result === 'completed') ? 'paid' : 'unpaid';
    $order_status = ($result === 'completed') ? 'completed' : 'cancelled';
    $tuu_message = ($result === 'completed') ? 'Transaccion aprobada' : "Transaccion $result";

    // Actualizar tuu_orders
    $sql = "UPDATE tuu_orders SET 
            status = ?, 
            payment_status = ?,
            order_status = ?,
            tuu_transaction_id = ?,
            tuu_message = ?,
            tuu_amount = ?,
            tuu_timestamp = NOW(),
            updated_at = NOW()
            WHERE order_number = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $payment_status, $order_status, $transaction_id, $tuu_message, $amount, $order_id]);

    // Obtener datos de la orden
    $order_sql = "SELECT user_id, customer_name, product_price FROM tuu_orders WHERE order_number = ?";
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([$order_id]);
    $order_data = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order_data || !$order_data['user_id']) {
        throw new Exception('Orden no encontrada');
    }

    // VALIDACIÓN CRÍTICA: Solo ejecutar refund si pago aprobado
    if ($payment_status === 'paid' && $tuu_message === 'Transaccion aprobada') {
        
        // 1. Insertar refund en rl6_credit_transactions
        $refund_sql = "INSERT INTO rl6_credit_transactions 
                      (user_id, amount, type, description, order_id) 
                      VALUES (?, ?, 'refund', 'Reembolso - Crédito pagado', ?)";
        $refund_stmt = $pdo->prepare($refund_sql);
        $refund_stmt->execute([$order_data['user_id'], $order_data['product_price'], $order_id]);
        
        // 2. Resetear credito_usado a 0
        $reset_sql = "UPDATE usuarios SET credito_usado = 0 WHERE id = ?";
        $reset_stmt = $pdo->prepare($reset_sql);
        $reset_stmt->execute([$order_data['user_id']]);
        
        // 3. Enviar email de confirmación
        try {
            $ch = curl_init('https://caja.laruta11.cl/api/gmail/send_payment_confirmation.php');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'user_id' => $order_data['user_id'],
                'order_id' => $order_id,
                'amount' => $order_data['product_price']
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_exec($ch);
            curl_close($ch);
        } catch (Exception $email_error) {
            error_log("RL6 Payment - Error enviando email: " . $email_error->getMessage());
        }
        
        error_log("RL6 Payment SUCCESS - User: {$order_data['user_id']}, Order: $order_id, Amount: {$order_data['product_price']}");
    } else {
        error_log("RL6 Payment FAILED - Order: $order_id, Status: $payment_status, Message: $tuu_message");
    }

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'status' => $new_status,
        'payment_processed' => ($payment_status === 'paid' && $tuu_message === 'Transaccion aprobada')
    ]);

} catch (Exception $e) {
    error_log("RL6 Payment Callback Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
