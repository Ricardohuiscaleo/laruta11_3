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
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'] ?? [];
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Guardar pedido (como antes, pero con detalle de productos)
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $pdo->beginTransaction();
        
        if (!empty($cart_items)) {
            // Crear descripción de productos
            $product_summary = count($cart_items) . ' productos: ' . 
                implode(', ', array_slice(array_map(function($item) {
                    return $item['name'] . ' x' . $item['quantity'];
                }, $cart_items), 0, 3)) . 
                (count($cart_items) > 3 ? '...' : '');
            
            // Guardar pedido principal con detalle y delivery fee
            $order_sql = "INSERT INTO tuu_orders (
                order_number, user_id, customer_name, customer_phone, 
                product_name, product_price, delivery_fee, installment_amount, has_item_details, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, 'pending')";
            
            $order_stmt = $pdo->prepare($order_sql);
            $order_stmt->execute([
                $order_id, $user_id, $customer_name, $customer_phone,
                $product_summary, $amount, $delivery_fee, $amount
            ]);
            
            $order_db_id = $pdo->lastInsertId();
            
            // Guardar items específicos
            $item_sql = "INSERT INTO tuu_order_items (
                order_id, order_reference, product_id, product_name, 
                product_price, quantity, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $item_stmt = $pdo->prepare($item_sql);
            
            foreach ($cart_items as $item) {
                $product_id = $item['id'] ?? null;
                $product_name = $item['name'] ?? 'Producto sin nombre';
                $product_price = $item['price'] ?? 0;
                $quantity = $item['quantity'] ?? 1;
                $subtotal = $product_price * $quantity;
                
                // Detectar si es combo y preparar datos
                $is_combo = isset($item['type']) && $item['type'] === 'combo' || 
                           isset($item['category_name']) && $item['category_name'] === 'Combos' ||
                           isset($item['selections']);
                
                $item_type = $is_combo ? 'combo' : 'product';
                $combo_data = null;
                
                if ($is_combo) {
                    $combo_data = json_encode([
                        'fixed_items' => $item['fixed_items'] ?? [],
                        'selections' => $item['selections'] ?? [],
                        'combo_id' => $item['combo_id'] ?? null
                    ]);
                }
                
                // Verificar si columna combo_data existe
                $check_column = $pdo->query("SHOW COLUMNS FROM tuu_order_items LIKE 'combo_data'");
                
                if ($check_column->rowCount() > 0) {
                    // Usar query con combo_data e item_type
                    $item_sql_combo = "INSERT INTO tuu_order_items (
                        order_id, order_reference, product_id, item_type, combo_data,
                        product_name, product_price, quantity, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $item_stmt_combo = $pdo->prepare($item_sql_combo);
                    $item_stmt_combo->execute([
                        $order_db_id, $order_id, $product_id, $item_type, $combo_data,
                        $product_name, $product_price, $quantity, $subtotal
                    ]);
                } else {
                    // Fallback sin combo_data
                    $item_stmt->execute([
                        $order_db_id, $order_id, $product_id, $product_name,
                        $product_price, $quantity, $subtotal
                    ]);
                }
            }
            
            error_log("Order saved with items: $order_id, Items: " . count($cart_items));
        } else {
            // Pedido básico (como antes)
            $order_sql = "INSERT INTO tuu_orders (
                order_number, user_id, customer_name, customer_phone, 
                product_name, product_price, installment_amount, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $order_stmt = $pdo->prepare($order_sql);
            $order_stmt->execute([
                $order_id, $user_id, $customer_name, $customer_phone,
                'Pedido La Ruta 11', $amount, $amount
            ]);
            
            error_log("Basic order saved: $order_id");
        }
        
        $pdo->commit();
    } catch (Exception $db_error) {
        if (isset($pdo)) $pdo->rollBack();
        error_log("Error BD: " . $db_error->getMessage());
    }
    
    // PASO 1: Obtener Token TUU
    $url_base = 'https://core.payment.haulmer.com/api/v1/payment';
    $token_url = $url_base . '/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
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
    
    // DECODIFICAR JWT DIRECTAMENTE (sin validación adicional)
    $jwt_parts = explode('.', $token_data['token']);
    if (count($jwt_parts) !== 3) {
        throw new Exception('Token JWT inválido');
    }
    
    $payload = json_decode(base64_decode($jwt_parts[1]), true);
    if (!isset($payload['secret_key']) || !isset($payload['account_id'])) {
        throw new Exception('Token JWT no contiene datos necesarios');
    }
    
    $secret_key = $payload['secret_key'];
    $account_id = $payload['account_id'];
    
    // PASO 2: Crear Transacción con Firma HMAC
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
        'x_url_complete' => 'https://app.laruta11.cl/payment-success',
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
    
    // PASO 3: Envío a TUU
    $ch = curl_init($url_base);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($transaction_data));
    
    $payment_response = curl_exec($ch);
    $payment_httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($payment_httpCode !== 200) {
        error_log("TUU Payment Error - HTTP $payment_httpCode: $payment_response");
        throw new Exception("Error creando pago TUU - HTTP $payment_httpCode");
    }
    
    // TUU devuelve directamente la URL de Webpay
    $webpay_url = trim($payment_response, '"');
    
    if (!filter_var($webpay_url, FILTER_VALIDATE_URL)) {
        error_log("TUU Payment Response: $payment_response");
        throw new Exception('URL de pago inválida recibida de TUU');
    }
    
    echo json_encode([
        'success' => true,
        'payment_url' => $webpay_url,
        'order_id' => $order_id,
        'user_tracked' => $user_id ? true : false
    ]);
    
} catch (Exception $e) {
    error_log("TUU Payment Exception: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>