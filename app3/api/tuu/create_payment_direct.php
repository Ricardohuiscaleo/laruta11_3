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
                product_name, product_price, delivery_fee, installment_amount, 
                has_item_details, status, payment_status, order_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, 'pending', 'unpaid', 'pending')";
            
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
                error_log("Processing item: " . json_encode($item));
                $product_id = $item['id'] ?? null;
                $product_name = $item['name'] ?? 'Producto sin nombre';
                $product_price = $item['price'] ?? 0;
                $quantity = $item['quantity'] ?? 1;
                $subtotal = $product_price * $quantity;
                
                // Calcular item_cost desde receta o cost_price
                $item_cost = 0;
                if ($product_id) {
                    // Intentar calcular desde receta
                    $recipe_stmt = $pdo->prepare("
                        SELECT SUM(i.cost_per_unit * pr.quantity * 
                            CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                        ) as recipe_cost
                        FROM product_recipes pr
                        JOIN ingredients i ON pr.ingredient_id = i.id
                        WHERE pr.product_id = ? AND i.is_active = 1
                    ");
                    $recipe_stmt->execute([$product_id]);
                    $recipe_result = $recipe_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($recipe_result && $recipe_result['recipe_cost'] > 0) {
                        $item_cost = $recipe_result['recipe_cost'];
                    } else {
                        // Usar cost_price del producto
                        $cost_stmt = $pdo->prepare("SELECT cost_price FROM products WHERE id = ?");
                        $cost_stmt->execute([$product_id]);
                        $cost_result = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                        $item_cost = $cost_result['cost_price'] ?? 0;
                    }
                }
                
                // Detectar si es combo y preparar datos
                $is_combo = isset($item['type']) && $item['type'] === 'combo' || 
                           isset($item['category_name']) && $item['category_name'] === 'Combos' ||
                           isset($item['selections']);
                
                $has_customizations = isset($item['customizations']) && !empty($item['customizations']);
                error_log("has_customizations: " . ($has_customizations ? 'YES' : 'NO'));
                if ($has_customizations) {
                    error_log("customizations data: " . json_encode($item['customizations']));
                }
                
                $item_type = $is_combo ? 'combo' : 'product';
                $combo_data = null;
                
                if ($is_combo) {
                    $combo_data_array = [
                        'fixed_items' => $item['fixed_items'] ?? [],
                        'selections' => $item['selections'] ?? [],
                        'combo_id' => $item['combo_id'] ?? null
                    ];
                    
                    // Agregar customizations si existen (extras personalizados del combo)
                    if ($has_customizations) {
                        $combo_data_array['customizations'] = $item['customizations'];
                    }
                    
                    $combo_data = json_encode($combo_data_array);
                    
                    // Calcular costo del combo sumando productos incluidos
                    $combo_cost = 0;
                    
                    // Sumar fixed_items
                    if (isset($item['fixed_items'])) {
                        foreach ($item['fixed_items'] as $fixed) {
                            $fixed_id = $fixed['product_id'] ?? $fixed['id'] ?? null;
                            if ($fixed_id) {
                                $fixed_cost_stmt = $pdo->prepare("
                                    SELECT COALESCE(
                                        (SELECT SUM(i.cost_per_unit * pr.quantity * CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END)
                                         FROM product_recipes pr
                                         JOIN ingredients i ON pr.ingredient_id = i.id
                                         WHERE pr.product_id = ? AND i.is_active = 1),
                                        (SELECT cost_price FROM products WHERE id = ?),
                                        0
                                    ) as fixed_cost
                                ");
                                $fixed_cost_stmt->execute([$fixed_id, $fixed_id]);
                                $fixed_result = $fixed_cost_stmt->fetch(PDO::FETCH_ASSOC);
                                $combo_cost += $fixed_result['fixed_cost'] ?? 0;
                            }
                        }
                    }
                    
                    // Sumar selections
                    if (isset($item['selections'])) {
                        foreach ($item['selections'] as $selection) {
                            if (is_array($selection)) {
                                foreach ($selection as $sel) {
                                    $sel_id = $sel['id'] ?? null;
                                    if ($sel_id) {
                                        $sel_cost_stmt = $pdo->prepare("
                                            SELECT COALESCE(
                                                (SELECT SUM(i.cost_per_unit * pr.quantity * CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END)
                                                 FROM product_recipes pr
                                                 JOIN ingredients i ON pr.ingredient_id = i.id
                                                 WHERE pr.product_id = ? AND i.is_active = 1),
                                                (SELECT cost_price FROM products WHERE id = ?),
                                                0
                                            ) as sel_cost
                                        ");
                                        $sel_cost_stmt->execute([$sel_id, $sel_id]);
                                        $sel_result = $sel_cost_stmt->fetch(PDO::FETCH_ASSOC);
                                        $combo_cost += $sel_result['sel_cost'] ?? 0;
                                    }
                                }
                            } else {
                                $sel_id = $selection['id'] ?? null;
                                if ($sel_id) {
                                    $sel_cost_stmt = $pdo->prepare("
                                        SELECT COALESCE(
                                            (SELECT SUM(i.cost_per_unit * pr.quantity * CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END)
                                             FROM product_recipes pr
                                             JOIN ingredients i ON pr.ingredient_id = i.id
                                             WHERE pr.product_id = ? AND i.is_active = 1),
                                            (SELECT cost_price FROM products WHERE id = ?),
                                            0
                                        ) as sel_cost
                                    ");
                                    $sel_cost_stmt->execute([$sel_id, $sel_id]);
                                    $sel_result = $sel_cost_stmt->fetch(PDO::FETCH_ASSOC);
                                    $combo_cost += $sel_result['sel_cost'] ?? 0;
                                }
                            }
                        }
                    }
                    
                    // Sumar costo de customizations si existen (extras del combo)
                    if ($has_customizations) {
                        foreach ($item['customizations'] as $custom) {
                            $custom_id = $custom['id'] ?? null;
                            if ($custom_id) {
                                $custom_cost_stmt = $pdo->prepare("
                                    SELECT COALESCE(
                                        (SELECT SUM(i.cost_per_unit * pr.quantity * CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END)
                                         FROM product_recipes pr
                                         JOIN ingredients i ON pr.ingredient_id = i.id
                                         WHERE pr.product_id = ? AND i.is_active = 1),
                                        (SELECT cost_price FROM products WHERE id = ?),
                                        0
                                    ) as custom_cost
                                ");
                                $custom_cost_stmt->execute([$custom_id, $custom_id]);
                                $custom_result = $custom_cost_stmt->fetch(PDO::FETCH_ASSOC);
                                $custom_quantity = $custom['quantity'] ?? 1;
                                $combo_cost += ($custom_result['custom_cost'] ?? 0) * $custom_quantity;
                            }
                        }
                    }
                    
                    $item_cost = $combo_cost;
                    error_log("Saving combo_data for combo: $combo_data, item_cost: $item_cost");
                } elseif ($has_customizations) {
                    // Guardar personalizaciones como combo_data
                    $combo_data = json_encode([
                        'customizations' => $item['customizations']
                    ]);
                    
                    // Sumar costo de personalizaciones
                    foreach ($item['customizations'] as $custom) {
                        $custom_id = $custom['id'] ?? null;
                        if ($custom_id) {
                            $custom_cost_stmt = $pdo->prepare("
                                SELECT COALESCE(
                                    (SELECT SUM(i.cost_per_unit * pr.quantity * CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END)
                                     FROM product_recipes pr
                                     JOIN ingredients i ON pr.ingredient_id = i.id
                                     WHERE pr.product_id = ? AND i.is_active = 1),
                                    (SELECT cost_price FROM products WHERE id = ?),
                                    0
                                ) as custom_cost
                            ");
                            $custom_cost_stmt->execute([$custom_id, $custom_id]);
                            $custom_result = $custom_cost_stmt->fetch(PDO::FETCH_ASSOC);
                            $custom_quantity = $custom['quantity'] ?? 1;
                            $item_cost += ($custom_result['custom_cost'] ?? 0) * $custom_quantity;
                        }
                    }
                    
                    error_log("Saving combo_data for customizations: $combo_data, item_cost: $item_cost");
                }
                
                // Verificar si columna combo_data existe
                $check_column = $pdo->query("SHOW COLUMNS FROM tuu_order_items LIKE 'combo_data'");
                
                if ($check_column->rowCount() > 0) {
                    // Usar query con combo_data e item_type
                    $item_sql_combo = "INSERT INTO tuu_order_items (
                        order_id, order_reference, product_id, item_type, combo_data,
                        product_name, product_price, item_cost, quantity, subtotal
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $item_stmt_combo = $pdo->prepare($item_sql_combo);
                    $item_stmt_combo->execute([
                        $order_db_id, $order_id, $product_id, $item_type, $combo_data,
                        $product_name, $product_price, $item_cost, $quantity, $subtotal
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
                product_name, product_price, installment_amount, 
                status, payment_status, order_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', 'pending')";
            
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