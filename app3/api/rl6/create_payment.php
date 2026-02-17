<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config = require_once __DIR__ . '/../../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('user_id requerido');
    }
    
    // Obtener datos del usuario
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $user_sql = "SELECT id, nombre, email, telefono, credito_usado, grado_militar, unidad_trabajo 
                 FROM usuarios WHERE id = ? AND es_militar_rl6 = 1 AND credito_aprobado = 1";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado o sin crédito RL6 aprobado');
    }
    
    $amount = round($user['credito_usado']);
    
    if ($amount <= 0) {
        throw new Exception('No hay saldo pendiente de pago');
    }
    
    $order_id = 'RL6-' . time() . '-' . rand(1000, 9999);
    
    // Guardar en tuu_orders
    $order_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone, 
        product_name, product_price, installment_amount, 
        status, payment_status, payment_method, order_status,
        pagado_con_credito_rl6, monto_credito_rl6
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', 'webpay', 'pending', 0, 0)";
    
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([
        $order_id, 
        $user_id, 
        $user['nombre'], 
        $user['telefono'],
        'Pago Crédito RL6 - ' . $user['grado_militar'],
        $amount, 
        $amount
    ]);
    
    // PASO 1: Obtener Token TUU
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
    
    // PASO 2: Validar Token
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
    
    // PASO 3: Crear Transacción
    $transaction_data = [
        'platform' => 'ruta11rl6',
        'paymentMethod' => 'webpay',
        'x_account_id' => $account_id,
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $user['email'],
        'x_customer_first_name' => explode(' ', $user['nombre'])[0],
        'x_customer_last_name' => explode(' ', $user['nombre'])[1] ?? '',
        'x_customer_phone' => $user['telefono'],
        'x_description' => 'Pago Crédito RL6 - ' . $user['grado_militar'],
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta 11 - Crédito RL6',
        'x_url_callback' => 'https://app.laruta11.cl/api/rl6/payment_callback.php',
        'x_url_cancel' => 'https://app.laruta11.cl/credito?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/credito?success=1',
        'secret' => '18756627',
        'dte_type' => 48
    ];
    
    // Generar firma HMAC
    ksort($transaction_data);
    $firmar = '';
    foreach ($transaction_data as $llave => $valor) {
        if (strpos($llave, 'x_') === 0) {
            $firmar .= $llave . $valor;
        }
    }
    $transaction_data['x_signature'] = hash_hmac('sha256', $firmar, $secret_key);
    
    $transaction_data['dte'] = [
        'net_amount' => $amount,
        'exempt_amount' => 1,
        'type' => 48
    ];
    
    // PASO 4: Envío a TUU
    $ch = curl_init($url_base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($payment_httpCode !== 200) {
        throw new Exception('Error creando pago TUU');
    }
    
    $webpay_url = trim($payment_response, '"');
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de pago inválida');
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id,
        'amount' => $amount
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
