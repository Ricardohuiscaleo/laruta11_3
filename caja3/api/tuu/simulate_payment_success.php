<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['order'])) {
    echo json_encode(['success' => false, 'error' => 'Order reference required']);
    exit;
}

try {
    // Crear conexión PDO
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
        "u958525313_app",
        "wEzho0-hujzoz-cevzin",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $orderRef = $_GET['order'];
    
    // Marcar orden como pagada
    $stmt = $pdo->prepare("
        UPDATE tuu_orders 
        SET payment_status = 'paid', 
            order_status = 'sent_to_kitchen',
            tuu_transaction_id = ?,
            tuu_amount = product_price,
            tuu_message = 'Pago simulado exitoso'
        WHERE order_number = ?
    ");
    
    $transactionId = 'SIM-' . time();
    $stmt->execute([$transactionId, $orderRef]);
    
    if ($stmt->rowCount() > 0) {
        // Redirigir a payment-success
        header("Location: https://app.laruta11.cl/payment-success?order={$orderRef}&amount=" . ($_GET['amount'] ?? '0') . "&x_transaction_id={$transactionId}");
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Order not found']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>