<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Crear conexión PDO
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
        "u958525313_app",
        "wEzho0-hujzoz-cevzin",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Generar order reference único
    $orderRef = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Crear orden en tuu_orders con extras y notas
    $stmt = $pdo->prepare("
        INSERT INTO tuu_orders (
            order_number, user_id, customer_name, customer_phone, 
            product_name, product_price, installment_amount,
            delivery_type, delivery_address, customer_notes,
            status, payment_status, order_status, delivery_fee
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', 'pending', ?)
    ");
    
    $productNames = array_map(function($item) { return $item['name']; }, $input['cart_items']);
    $productNamesStr = implode(', ', $productNames);
    
    $stmt->execute([
        $orderRef,
        $input['user_id'] ?? null,
        $input['customer_name'],
        $input['customer_phone'] ?? null,
        $productNamesStr,
        $input['amount'],
        $input['amount'],
        $input['delivery_type'] ?? 'pickup',
        $input['delivery_address'] ?? null,
        $input['customer_notes'] ?? null,
        $input['delivery_fee'] ?? 0
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Guardar items del carrito con clasificación de extras
    $hardcodedExtras = [401, 402, 403, 404, 405, 301, 304, 306, 307, 308, 309];
    
    foreach ($input['cart_items'] as $item) {
        $itemType = 'product';
        
        if (in_array($item['id'], $hardcodedExtras)) {
            if (in_array($item['id'], [401, 402, 403, 404, 405])) {
                $itemType = 'personalizar';
            } else {
                $itemType = 'extras';
            }
        } elseif ($item['id'] >= 200 && $item['id'] < 300) {
            $itemType = 'acompañamiento';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tuu_order_items (
                order_id, order_reference, product_id, item_type,
                product_name, product_price, quantity, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $orderId,
            $orderRef,
            $item['id'],
            $itemType,
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['price'] * $item['quantity']
        ]);
    }
    
    // USAR LA LÓGICA TUU FUNCIONAL DEL README
    $config = [
        'tuu_online_rut' => '78194739-3',
        'tuu_online_secret' => '4bd3b7629ea289797fda5a988c1e2a6dee8f710b883657f7cbed7ce0ad5a09397e2c7698fda707da'
    ];
    
    $_ENV['URL_PRODUCCION'] = 'https://core.payment.haulmer.com/api/v1/payment';
    $_ENV['SECRET'] = '18756627';
    
    // Paso 1: Obtener Token TUU
    $token_url = "https://core.payment.haulmer.com/api/v1/payment/token/" . $config['tuu_online_rut'];
    $token_headers = [
        'Authorization: Bearer ' . $config['tuu_online_secret'],
        'Content-Type: application/json'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $token_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $token_headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $token_response = curl_exec($ch);
    curl_close($ch);
    
    $token_data = json_decode($token_response, true);
    
    // Paso 2: Decodificar JWT directamente
    $jwt_parts = explode('.', $token_data['token']);
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    $secret_key = $payload['secret_key'];
    $account_id = $payload['account_id'];
    
    // Paso 3: Crear transacción con firma HMAC
    $transaction_data = [
        'platform' => 'ruta11app',
        'paymentMethod' => 'webpay',
        'x_account_id' => $account_id,
        'x_amount' => $input['amount'],
        'x_currency' => 'CLP',
        'x_customer_email' => $input['customer_email'] ?? $input['customer_phone'] . '@ruta11.cl',
        'x_customer_first_name' => $input['customer_name'],
        'x_customer_phone' => $input['customer_phone'] ?? '',
        'x_description' => 'Pedido La Ruta 11 - ' . $orderRef,
        'x_reference' => $orderRef,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta 11',
        'x_url_callback' => 'https://app.laruta11.cl/api/tuu/callback.php',
        'x_url_cancel' => 'https://app.laruta11.cl/checkout?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/payment-success?order=' . $orderRef . '&amount=' . $input['amount'],
        'secret' => $_ENV['SECRET'],
        'dte_type' => 48
    ];
    
    // Generar firma HMAC SHA256
    ksort($transaction_data);
    $firmar = '';
    foreach ($transaction_data as $llave => $valor) {
        if (strpos($llave, 'x_') === 0) {
            $firmar .= $llave . $valor;
        }
    }
    $transaction_data['x_signature'] = hash_hmac('sha256', $firmar, $secret_key);
    
    // Agregar estructura DTE
    $transaction_data['dte'] = [
        'net_amount' => $input['amount'],
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // Paso 4: Envío a TUU
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $_ENV['URL_PRODUCCION']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $payment_response = curl_exec($ch);
    curl_close($ch);
    
    // La respuesta es directamente la URL de Webpay
    $payment_url = trim($payment_response, '"');
    
    echo json_encode([
        'success' => true,
        'payment_url' => $payment_url,
        'order_reference' => $orderRef
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>