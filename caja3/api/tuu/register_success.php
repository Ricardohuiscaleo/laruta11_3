<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://app.laruta11.cl');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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
    // Obtener datos de TUU (GET o POST)
    $input = json_decode(file_get_contents('php://input'), true);
    
    $order_id = $input['order'] ?? $_GET['order'] ?? null;
    $transaction_id = $input['transaction_id'] ?? $_GET['x_transaction_id'] ?? null;
    $amount = $input['amount'] ?? $_GET['x_amount'] ?? null;
    $timestamp = $input['timestamp'] ?? $_GET['x_timestamp'] ?? null;
    $message = $input['message'] ?? $_GET['x_message'] ?? null;
    $result = $input['result'] ?? $_GET['x_result'] ?? 'completed';
    $account_id = $input['account_id'] ?? $_GET['x_account_id'] ?? null;
    $currency = $input['currency'] ?? $_GET['x_currency'] ?? null;
    $signature = $input['signature'] ?? $_GET['x_signature'] ?? null;
    
    if (!$order_id) {
        throw new Exception('Order ID requerido');
    }

    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Actualizar tuu_orders con todos los datos de TUU
    $sql = "UPDATE tuu_orders SET 
            status = ?,
            payment_status = 'paid',
            tuu_transaction_id = ?,
            tuu_amount = ?,
            tuu_timestamp = ?,
            tuu_message = ?,
            tuu_account_id = ?,
            tuu_currency = ?,
            tuu_signature = ?,
            updated_at = NOW()
            WHERE order_number = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $result,
        $transaction_id,
        $amount,
        $timestamp,
        $message,
        $account_id,
        $currency,
        $signature,
        $order_id
    ]);
    
    // Log completo para debug
    error_log("TUU Success: Order $order_id, Transaction ID: $transaction_id, Amount: $amount, Currency: $currency, Account: $account_id, Timestamp: $timestamp");

    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado con todos los datos de TUU',
        'order_id' => $order_id,
        'transaction_id' => $transaction_id,
        'amount' => $amount,
        'currency' => $currency,
        'account_id' => $account_id,
        'timestamp' => $timestamp,
        'result' => $result
    ]);

} catch (Exception $e) {
    error_log("Register Success Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>