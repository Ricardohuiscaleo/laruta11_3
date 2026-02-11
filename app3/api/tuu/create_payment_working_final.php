<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $user_id = $input['user_id'] ?? null;
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Guardar en BD si hay user_id
    if ($user_id) {
        try {
            $pdo = new PDO(
                "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
                $config['app_db_user'],
                $config['app_db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $order_sql = "INSERT INTO tuu_orders (
                order_number, user_id, customer_name, customer_phone, 
                product_name, product_price, installment_amount, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $order_stmt = $pdo->prepare($order_sql);
            $order_stmt->execute([
                $order_id, $user_id, $customer_name, $customer_phone,
                'Pedido La Ruta 11', $amount, $amount
            ]);
        } catch (Exception $db_error) {
            error_log("Error BD: " . $db_error->getMessage());
        }
    }
    
    // PASO 1: Obtener Token TUU (SEGÚN README)
    $url_base = 'https://core.payment.haulmer.com/api/v1/payment';
    $token_url = $url_base . '/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error obteniendo token TUU');
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido');
    }
    
    // PASO 2: Validar Token (SEGÚN README)
    $validate_url = $url_base . '/validatetoken';
    $validate_data = ['token' => $token_data['token']];
    
    $ch = curl_init($validate_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($validate_data));
    
    $validate_response = curl_exec($ch);
    curl_close($ch);
    
    $validate_result = json_decode($validate_response, true);
    if (!isset($validate_result['secret_key'])) {
        throw new Exception('Error validando token TUU');
    }
    
    $secret_key = $validate_result['secret_key'];
    $account_id = $validate_result['account_id'];
    
    // PASO 3: Crear Transacción con Firma HMAC (SEGÚN README)
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
        'x_shop_name' => 'La Ruta 11',
        'x_url_callback' => 'https://app.laruta11.cl/api/tuu/callback.php',
        'x_url_cancel' => 'https://app.laruta11.cl/checkout?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/payment-success',
        'secret' => '18756627',
        'dte_type' => 48
    ];
    
    // Generar firma HMAC SHA256 (SEGÚN README)
    ksort($transaction_data);
    $firmar = '';
    foreach ($transaction_data as $llave => $valor) {
        if (strpos($llave, 'x_') === 0) {
            $firmar .= $llave . $valor;
        }
    }
    $transaction_data['x_signature'] = hash_hmac('sha256', $firmar, $secret_key);
    
    // Agregar estructura DTE (SEGÚN README)
    $transaction_data['dte'] = [
        'net_amount' => $amount,
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // PASO 4: Envío a TUU - ENDPOINT BASE (SEGÚN README)
    $ch = curl_init($url_base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($payment_httpCode !== 200) {
        throw new Exception('Error creando pago TUU: HTTP ' . $payment_httpCode);
    }
    
    // TUU devuelve directamente la URL de Webpay (SEGÚN README)
    $webpay_url = trim($payment_response, '"');
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de pago inválida recibida de TUU');
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id,
        'user_tracked' => $user_id ? true : false
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>