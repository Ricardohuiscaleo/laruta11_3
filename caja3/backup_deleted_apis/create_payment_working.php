<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración directa
$config = [
    'tuu_online_rut' => '78194739-3',
    'tuu_online_secret' => '4bd3b7629ea289797fda5a988c1e2a6dee8f710b883657f7cbed7ce0ad5a09397e2c7698fda707da'
];

// Variables de entorno del plugin
$_ENV['URL_DESARROLLO'] = 'https://frontend-api.payment.haulmer.dev/v1/payment';
$_ENV['URL_PRODUCCION'] = 'https://core.payment.haulmer.com/api/v1/payment';
$_ENV['SECRET'] = '18756627';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Obtener token TUU
    $token_url = 'https://core.payment.haulmer.com/api/v1/payment/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error obteniendo token: HTTP ' . $httpCode);
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido');
    }
    
    // Validar token
    $validate_url = 'https://core.payment.haulmer.com/api/v1/payment/validatetoken';
    $validate_data = json_encode(['token' => $token_data['token']]);
    
    $ch = curl_init($validate_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $validate_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $validate_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error validando token: HTTP ' . $httpCode);
    }
    
    $secret_keys = json_decode($validate_response, true);
    if (!isset($secret_keys['secret_key']) || !isset($secret_keys['account_id'])) {
        throw new Exception('Claves secretas no recibidas');
    }
    
    // Crear transacción usando el método exacto del SDK
    $transaction_data = [
        'platform' => 'ruta11app',
        'paymentMethod' => 'webpay',
        'x_account_id' => $secret_keys['account_id'],
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $customer_email,
        'x_customer_first_name' => $customer_name,
        'x_customer_last_name' => '',
        'x_customer_phone' => $customer_phone,
        'x_description' => 'Pedido La Ruta 11',
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta 11',
        'x_url_callback' => 'https://app.laruta11.cl/api/tuu/callback.php',
        'x_url_cancel' => 'https://app.laruta11.cl/checkout?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/payment-success',
        'secret' => $_ENV['SECRET'],
        'dte_type' => 48
    ];
    
    // Generar firma (igual que el SDK)
    ksort($transaction_data);
    $firmar = '';
    foreach ($transaction_data as $llave => $valor) {
        if (strpos($llave, 'x_') === 0) {
            $firmar .= $llave . $valor;
        }
    }
    $transaction_data['x_signature'] = hash_hmac('sha256', $firmar, $secret_keys['secret_key']);
    
    // Generar DTE (igual que el SDK)
    $transaction_data['dte'] = [
        'net_amount' => $amount,
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // Enviar a la URL correcta del SDK (no /create)
    $payment_url = $_ENV['URL_PRODUCCION'];
    
    $ch = curl_init($payment_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    $payment_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // El SDK retorna la URL directamente como string
    if ($httpCode === 200 && filter_var($payment_response, FILTER_VALIDATE_URL)) {
        echo json_encode([
            'success' => true,
            'payment_url' => $payment_response
        ]);
    } else {
        throw new Exception('Error creando pago: ' . $payment_response);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>