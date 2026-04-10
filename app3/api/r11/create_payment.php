<?php
header('Content-Type: application/json');

// CORS restringido
$allowed_origins = ['https://app.laruta11.cl', 'https://caja.laruta11.cl'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Session-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config = require_once __DIR__ . '/../../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    
    if (!$user_id) {
        throw new Exception('user_id requerido');
    }

    // Validar session_token
    $session_token = $_SERVER['HTTP_X_SESSION_TOKEN'] ?? $_COOKIE['session_token'] ?? null;
    if (!$session_token) {
        echo json_encode(['success' => false, 'error' => 'No autenticado']);
        exit;
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verificar que session_token corresponde al user_id
    $auth_sql = "SELECT id FROM usuarios WHERE session_token = ? AND activo = 1";
    $auth_stmt = $pdo->prepare($auth_sql);
    $auth_stmt->execute([$session_token]);
    $auth_user = $auth_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$auth_user || $auth_user['id'] != $user_id) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }

    // Obtener datos del usuario R11
    $user_sql = "SELECT id, nombre, email, telefono, credito_r11_usado, relacion_r11 
                 FROM usuarios WHERE id = ? AND es_credito_r11 = 1 AND credito_r11_aprobado = 1";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado o sin crédito R11 aprobado');
    }
    
    $amount = round($user['credito_r11_usado']);
    
    if ($amount <= 0) {
        throw new Exception('No hay saldo pendiente de pago');
    }
    
    $order_id = 'R11C-' . time() . '-' . rand(1000, 9999);
    
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
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("R11 Token Error - HTTP $httpCode: $token_response, CURL: $curl_error");
        throw new Exception("Error obteniendo token TUU - HTTP $httpCode");
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        error_log("R11 Token Response: $token_response");
        throw new Exception('Token no recibido de TUU');
    }
    
    // Decodificar JWT directamente
    $jwt_parts = explode('.', $token_data['token']);
    if (count($jwt_parts) !== 3) {
        error_log("R11 JWT Invalid: " . $token_data['token']);
        throw new Exception('Token JWT inválido');
    }
    
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    if (!isset($payload['secret_key']) || !isset($payload['account_id'])) {
        error_log("R11 JWT Payload: " . json_encode($payload));
        throw new Exception('Token JWT no contiene datos necesarios');
    }
    
    $secret_key = $payload['secret_key'];
    $account_id = $payload['account_id'];

    // PASO 2: Crear Transacción
    $transaction_data = [
        'platform' => 'ruta11r11',
        'paymentMethod' => 'webpay',
        'x_account_id' => $account_id,
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $user['email'],
        'x_customer_first_name' => explode(' ', $user['nombre'])[0],
        'x_customer_last_name' => explode(' ', $user['nombre'])[1] ?? '',
        'x_customer_phone' => $user['telefono'],
        'x_description' => 'Pago Crédito R11 - ' . $user['relacion_r11'],
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta 11 - Crédito R11',
        'x_url_callback' => 'https://app.laruta11.cl/api/r11/payment_callback.php',
        'x_url_cancel' => 'https://app.laruta11.cl/pagar-credito-r11?cancelled=1',
        'x_url_complete' => 'https://app.laruta11.cl/r11-payment-pending',
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
    
    // Guardar en tuu_orders
    $order_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone, 
        product_name, product_price, installment_amount, delivery_fee,
        status, payment_status, payment_method, order_status, delivery_type,
        pagado_con_credito_r11, monto_credito_r11, subtotal,
        discount_amount, discount_10, discount_30, discount_birthday, discount_pizza,
        delivery_discount, delivery_extras, cashback_used,
        tuu_account_id, tuu_currency, tuu_signature
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'pending', 'unpaid', 'webpay', 'pending', 'pickup', 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, ?, 'CLP', ?)";
    
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([
        $order_id, 
        $user_id, 
        $user['nombre'], 
        $user['telefono'],
        'Pago Crédito R11 - ' . $user['relacion_r11'],
        $amount, 
        $amount,
        $account_id,
        $transaction_data['x_signature']
    ]);

    // PASO 3: Envío a TUU
    $ch = curl_init($url_base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $payment_curl_error = curl_error($ch);
    curl_close($ch);
    
    // TUU puede responder 200 con URL o 302 con Location header
    if ($payment_httpCode === 302 || $payment_httpCode === 301) {
        preg_match('/Location:\s*(\S+)/i', $payment_response, $matches);
        $webpay_url = trim($matches[1] ?? '');
    } else if ($payment_httpCode === 200) {
        $header_size = strpos($payment_response, "\r\n\r\n");
        $body = substr($payment_response, $header_size + 4);
        $webpay_url = trim($body, '"');
    } else {
        error_log("R11 Payment Error - HTTP $payment_httpCode: $payment_response, CURL: $payment_curl_error");
        throw new Exception("Error creando pago TUU - HTTP $payment_httpCode");
    }
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        error_log("R11 Payment Response ($payment_httpCode): $payment_response");
        throw new Exception('URL de pago inválida recibida de TUU');
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id,
        'amount' => $amount
    ]);
    
} catch (Exception $e) {
    error_log("R11 Payment Exception: " . $e->getMessage() . " | Line: " . $e->getLine());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
