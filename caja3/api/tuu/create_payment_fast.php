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
    // Configuración directa (sin buscar config.php)
    $config = [
        'tuu_online_rut' => '78194739-3',
        'tuu_online_secret' => '4bd3b7629ea289797fda5a988c1e2a6dee8f710b883657f7cbed7ce0ad5a09397e2c7698fda707da'
    ];
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // PASO 1: Obtener Token TUU (con timeout corto)
    $token_url = 'https://core.payment.haulmer.com/api/v1/payment/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout más corto
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error obteniendo token TUU - HTTP $httpCode");
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido de TUU');
    }
    
    // PASO 2: Decodificar JWT directamente
    $jwt_parts = explode('.', $token_data['token']);
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    $secret_key = $payload['secret_key'];
    $account_id = $payload['account_id'];
    
    // PASO 3: Crear transacción con firma HMAC
    $transaction_data = [
        'platform' => 'ruta11app',
        'paymentMethod' => 'webpay',
        'x_account_id' => $account_id,
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $customer_email,
        'x_customer_first_name' => explode(' ', $customer_name)[0],
        'x_customer_last_name' => explode(' ', $customer_name)[1] ?? '',
        'x_customer_phone' => $customer_phone,
        'x_description' => 'Pedido La Ruta 11',
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta11 Foodtrucks',
        'x_url_callback' => 'https://app.laruta11.cl/api/tuu/callback_simple.php',
        'x_url_cancel' => 'https://app.laruta11.cl/checkout?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/payment-success?order=' . $order_id . '&amount=' . $amount,
        'secret' => '18756627',
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
        'net_amount' => $amount,
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // PASO 4: Envío a TUU (con timeout corto)
    $ch = curl_init('https://core.payment.haulmer.com/api/v1/payment');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12); // Timeout más corto
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($payment_httpCode !== 200) {
        throw new Exception("Error creando pago TUU - HTTP $payment_httpCode");
    }
    
    // TUU devuelve directamente la URL de Webpay
    $webpay_url = trim($payment_response, '"');
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de pago inválida recibida de TUU');
    }
    
    // Guardar orden básica en background (sin bloquear respuesta)
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
            "u958525313_app",
            "wEzho0-hujzoz-cevzin",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 2]
        );
        
        $stmt = $pdo->prepare("INSERT INTO tuu_orders (order_number, customer_name, customer_phone, product_name, product_price, installment_amount, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->execute([$order_id, $customer_name, $customer_phone, 'Pedido La Ruta 11', $amount, $amount]);
    } catch (Exception $db_error) {
        // No bloquear el pago por errores de BD
        error_log("DB Error (non-blocking): " . $db_error->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>