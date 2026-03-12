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

    $is_approved = ($message === 'Transaccion aprobada');

    // Actualizar tuu_orders — payment_status = 'paid' SOLO si TUU confirma aprobación
    $sql = "UPDATE tuu_orders SET 
            status = ?,
            payment_status = ?,
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
        $is_approved ? 'paid' : 'pending_payment',
        $transaction_id,
        $amount,
        $timestamp,
        $message,
        $account_id,
        $currency,
        $signature,
        $order_id
    ]);
    
    error_log("TUU register_success: Order $order_id, message: $message, approved: " . ($is_approved ? 'YES' : 'NO'));

    // Procesar inventario SOLO si TUU confirmó aprobación y aún no tiene transacciones
    if ($is_approved) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE order_reference = ?");
        $check->execute([$order_id]);
        if ($check->fetchColumn() == 0) {
            $items_stmt = $pdo->prepare("SELECT id, product_id, product_name, quantity, item_type, combo_data FROM tuu_order_items WHERE order_reference = ?");
            $items_stmt->execute([$order_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($order_items)) {
                $inventory_items = [];
                foreach ($order_items as $item) {
                    if ($item['item_type'] === 'combo' && $item['combo_data']) {
                        $cd = json_decode($item['combo_data'], true);
                        $inventory_items[] = [
                            'order_item_id' => $item['id'], 'id' => $item['product_id'],
                            'name' => $item['product_name'], 'cantidad' => $item['quantity'],
                            'is_combo' => true, 'combo_id' => $cd['combo_id'] ?? null,
                            'fixed_items' => $cd['fixed_items'] ?? [], 'selections' => $cd['selections'] ?? []
                        ];
                    } else {
                        $inv = ['order_item_id' => $item['id'], 'id' => $item['product_id'],
                                'name' => $item['product_name'], 'cantidad' => $item['quantity']];
                        if ($item['combo_data']) {
                            $cd = json_decode($item['combo_data'], true);
                            if (isset($cd['customizations'])) $inv['customizations'] = $cd['customizations'];
                        }
                        $inventory_items[] = $inv;
                    }
                }
                require_once __DIR__ . '/../process_sale_inventory_fn.php';
                $inv_result = processSaleInventory($pdo, $inventory_items, $order_id);
                error_log("register_success inventario $order_id: " . ($inv_result['success'] ? 'OK' : $inv_result['error']));
            }
        }
    }

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