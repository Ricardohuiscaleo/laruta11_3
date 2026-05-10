<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
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

// Task 5.1: Include centralized inventory function
require_once __DIR__ . '/process_sale_inventory_fn.php';

// Task 7.2: Include delivery config helper for centralized BD params
require_once __DIR__ . '/delivery/delivery_config_helper.php';

/**
 * Task 5.1: Convert cart_items + order_item_ids into the format expected by processSaleInventory().
 * @param array $cart_items - Cart items from client input
 * @param array $order_item_ids - Map of product_id => order_item_id from tuu_order_items inserts
 * @return array Items formatted for processSaleInventory()
 */
function buildInventoryItems(array $cart_items, array $order_item_ids): array {
    $items = [];
    foreach ($cart_items as $ci) {
        $product_id = $ci['id'] ?? null;
        if (!$product_id) continue;

        $is_combo = isset($ci['type']) && $ci['type'] === 'combo' ||
                    isset($ci['category_name']) && $ci['category_name'] === 'Combos' ||
                    isset($ci['selections']);

        if ($is_combo) {
            $items[] = [
                'id' => $product_id,
                'name' => $ci['name'] ?? '',
                'cantidad' => $ci['quantity'] ?? 1,
                'is_combo' => true,
                'combo_id' => $ci['combo_id'] ?? $product_id,
                'fixed_items' => $ci['fixed_items'] ?? [],
                'selections' => $ci['selections'] ?? [],
                'order_item_id' => $order_item_ids[$product_id] ?? null,
                'customizations' => $ci['customizations'] ?? [],
            ];
        } else {
            $items[] = [
                'id' => $product_id,
                'name' => $ci['name'] ?? '',
                'cantidad' => $ci['quantity'] ?? 1,
                'order_item_id' => $order_item_ids[$product_id] ?? null,
                'customizations' => $ci['customizations'] ?? [],
            ];
        }
    }
    return $items;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validar datos requeridos
    if (empty($input['customer_name'])) {
        throw new Exception('Nombre del cliente es requerido');
    }
    if (empty($input['customer_phone'])) {
        throw new Exception('Teléfono del cliente es requerido');
    }
    if (empty($input['amount']) || $input['amount'] <= 0) {
        throw new Exception('Monto inválido');
    }
    if (empty($input['cart_items']) || !is_array($input['cart_items'])) {
        throw new Exception('Carrito vacío');
    }
    
    // Protección anti-duplicados: verificar si ya existe una orden reciente del mismo cliente con mismo monto
    $pdo_check = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $dup_stmt = $pdo_check->prepare("SELECT order_number FROM tuu_orders WHERE customer_name = ? AND installment_amount = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND) LIMIT 1");
    $dup_stmt->execute([trim($input['customer_name']), round($input['amount'])]);
    $dup_order = $dup_stmt->fetch(PDO::FETCH_ASSOC);
    if ($dup_order) {
        echo json_encode(['success' => true, 'order_id' => $dup_order['order_number'], 'duplicate' => true]);
        exit;
    }
    
    $amount = round($input['amount']);
    $customer_name = trim($input['customer_name']);
    $customer_phone = trim($input['customer_phone']);
    $customer_email = $input['customer_email'] ?? $customer_phone . '@ruta11.cl';
    $user_id = $input['user_id'] ?? null;
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'];
    $payment_method = $input['payment_method'] ?? 'cash'; // cash, card, transfer, rl6_credit, r11_credit
    
    // Prefijo según método de pago
    if ($payment_method === 'r11_credit') {
        $order_id = 'R11C-' . time() . '-' . rand(1000, 9999);
    } else {
        $order_id = 'T11-' . time() . '-' . rand(1000, 9999);
    }
    
    error_log("Creating order $order_id for $customer_name, payment: $payment_method");
    
    // Crédito RL6 y R11 se marcan como pagado automáticamente
    $payment_status = in_array($payment_method, ['rl6_credit', 'r11_credit']) ? 'paid' : 'unpaid';
    // TODAS las órdenes van directo a cocina para que aparezcan en comandas
    $order_status = 'sent_to_kitchen';
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $pdo->beginTransaction();
    
    // === Task 5.3: SERVER-SIDE SUBTOTAL CALCULATION ===
    $calculated_subtotal = 0;
    if (!empty($cart_items)) {
        foreach ($cart_items as $ci) {
            $ci_id = $ci['id'] ?? null;
            $ci_qty = $ci['quantity'] ?? 1;
            $db_price = $ci['price'] ?? 0; // fallback to client price

            if ($ci_id) {
                $price_stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                $price_stmt->execute([$ci_id]);
                $price_row = $price_stmt->fetch(PDO::FETCH_ASSOC);
                if ($price_row) {
                    $db_price = (int)$price_row['price'];
                }
            }

            $calculated_subtotal += $db_price * $ci_qty;

            // Add customization prices
            if (!empty($ci['customizations'])) {
                foreach ($ci['customizations'] as $cust) {
                    $cust_id = $cust['id'] ?? null;
                    $cust_qty = $cust['quantity'] ?? 1;
                    $cust_price = $cust['price'] ?? 0;

                    if ($cust_id) {
                        $cust_price_stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
                        $cust_price_stmt->execute([$cust_id]);
                        $cust_price_row = $cust_price_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($cust_price_row) {
                            $cust_price = (int)$cust_price_row['price'];
                        }
                    }

                    $calculated_subtotal += $cust_price * $cust_qty;
                }
            }
        }
    }

    // === Task 5.3: SERVER-SIDE DELIVERY FEE CALCULATION ===
    $delivery_type = $input['delivery_type'] ?? 'pickup';
    $calculated_delivery_fee = 0;

    if ($delivery_type === 'pickup') {
        $calculated_delivery_fee = 0;
    } elseif ($delivery_type === 'delivery') {
        $client_delivery_fee = (int)($input['delivery_fee'] ?? 0);
        $delivery_address = $input['delivery_address'] ?? null;

        try {
            // Get active truck location and base fee
            $truck_stmt = $pdo->query("SELECT latitud, longitud, tarifa_delivery FROM food_trucks WHERE activo = 1 ORDER BY id ASC LIMIT 1");
            $truck = $truck_stmt->fetch(PDO::FETCH_ASSOC);

            if ($truck && $delivery_address) {
                $truck_lat = (float)$truck['latitud'];
                $truck_lng = (float)$truck['longitud'];
                $base_fee = (int)$truck['tarifa_delivery'];

                // Geocode delivery address
                $api_key = $config['ruta11_google_maps_api_key'] ?? $config['google_maps_api_key'] ?? '';
                $addr = $delivery_address;
                if (stripos($addr, 'arica') === false) {
                    $addr .= ', Arica, Chile';
                }
                $encoded_addr = urlencode($addr);
                $geo_url = "https://maps.googleapis.com/maps/api/geocode/json?address={$encoded_addr}&key={$api_key}&language=es&region=cl";
                $geo_response = @file_get_contents($geo_url);
                $geo_data = json_decode($geo_response, true);

                if ($geo_data && $geo_data['status'] === 'OK' && !empty($geo_data['results'])) {
                    $dest_lat = $geo_data['results'][0]['geometry']['location']['lat'];
                    $dest_lng = $geo_data['results'][0]['geometry']['location']['lng'];

                    // Try Google Directions, fallback to Haversine
                    $distance_km = null;
                    $dir_url = "https://maps.googleapis.com/maps/api/directions/json?origin={$truck_lat},{$truck_lng}&destination={$dest_lat},{$dest_lng}&key={$api_key}&mode=driving";
                    $dir_response = @file_get_contents($dir_url);
                    $dir_data = json_decode($dir_response, true);

                    if ($dir_data && $dir_data['status'] === 'OK' && !empty($dir_data['routes'])) {
                        $distance_km = round($dir_data['routes'][0]['legs'][0]['distance']['value'] / 1000, 1);
                    } else {
                        // Haversine fallback
                        $R = 6371;
                        $dLat = deg2rad($dest_lat - $truck_lat);
                        $dLng = deg2rad($dest_lng - $truck_lng);
                        $a = sin($dLat/2)*sin($dLat/2) + cos(deg2rad($truck_lat))*cos(deg2rad($dest_lat))*sin($dLng/2)*sin($dLng/2);
                        $distance_km = round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
                    }

                    // Task 7.2: Read distance params from BD config
                    $delivery_config = get_delivery_config($pdo);
                    $distance_threshold_km = $delivery_config['distance_threshold_km'];
                    $surcharge_per_bracket = $delivery_config['surcharge_per_bracket'];
                    $bracket_size_km       = $delivery_config['bracket_size_km'];

                    // Calculate surcharge using BD params
                    $surcharge = 0;
                    if ($distance_km > $distance_threshold_km) {
                        $extra_km = $distance_km - $distance_threshold_km;
                        $brackets = ceil($extra_km / $bracket_size_km);
                        $surcharge = $brackets * $surcharge_per_bracket;
                    }
                    $calculated_delivery_fee = $base_fee + $surcharge;
                } else {
                    // Geocoding failed — fallback to client value
                    $calculated_delivery_fee = $client_delivery_fee;
                    error_log("create_order: delivery fee geocoding failed for '{$delivery_address}', using client value: {$client_delivery_fee}");
                }
            } else {
                // No truck or no address — fallback to client value
                $calculated_delivery_fee = $client_delivery_fee;
                error_log("create_order: no truck or address, using client value: {$client_delivery_fee}");
            }
        } catch (Exception $fee_err) {
            // Any error — fallback to client value
            $calculated_delivery_fee = $client_delivery_fee;
            error_log("create_order: delivery fee calculation error: " . $fee_err->getMessage() . ", using client value: {$client_delivery_fee}");
        }
    }

    // Override client-provided values with server-calculated ones
    // Store delivery_fee as NET of discount to match caja3 convention
    $delivery_fee = $calculated_delivery_fee - $delivery_discount;
    if ($delivery_fee < 0) {
        $delivery_fee = 0;
    }

    // === Task 7.2: card_surcharge — read from BD, validate, store separately ===
    // Read delivery config (may already be loaded above, but safe to call again — helper is idempotent)
    if (!isset($delivery_config)) {
        $delivery_config = get_delivery_config($pdo);
    }
    $bd_card_surcharge = $delivery_config['card_surcharge']; // default: 500

    // Determine card_surcharge: only applies to delivery + card payment
    $card_surcharge = 0;
    if (($input['delivery_type'] ?? 'pickup') === 'delivery' && ($input['payment_method'] ?? 'cash') === 'card') {
        $client_card_surcharge = (int)($input['card_surcharge'] ?? 0);
        if ($client_card_surcharge > 0 && $client_card_surcharge !== $bd_card_surcharge) {
            error_log("create_order: card_surcharge mismatch — client={$client_card_surcharge}, BD={$bd_card_surcharge}. Using BD value.");
        }
        // BD value is source of truth (Req 8.3)
        $card_surcharge = $bd_card_surcharge;
    }

    // === Task 5.4: SERVER-SIDE TOTAL RECALCULATION ===
    $discount_amount = (int)($input['discount_amount'] ?? 0);
    $delivery_discount = (int)($input['delivery_discount'] ?? 0);
    $delivery_extras_total = (int)($input['delivery_extras_total'] ?? 0);
    $cashback_used = (int)($input['cashback_used'] ?? 0);

    $calculated_total = $calculated_subtotal + $calculated_delivery_fee + $card_surcharge + $delivery_extras_total - $discount_amount - $delivery_discount - $cashback_used;
    if ($calculated_total < 0) {
        $calculated_total = 0;
    }

    // Use server-calculated total for product_price and installment_amount
    $amount = $calculated_total;

    // Crear descripción de productos
    $product_summary = count($cart_items) . ' productos: ' . 
        implode(', ', array_slice(array_map(function($item) {
            return $item['name'] . ' x' . $item['quantity'];
        }, $cart_items), 0, 3)) . 
        (count($cart_items) > 3 ? '...' : '');
    
    // Guardar orden principal
    $order_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone, 
        product_name, product_price, delivery_fee, card_surcharge, installment_amount, 
        has_item_details, status, payment_status, payment_method, order_status, delivery_type, 
        delivery_address, pickup_time, customer_notes, subtotal, discount_amount, delivery_discount, 
        delivery_extras, delivery_extras_items, cashback_used, scheduled_time, is_scheduled,
        delivery_distance_km, delivery_duration_min
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $delivery_extras_json = null;
    if (!empty($input['delivery_extras']) && is_array($input['delivery_extras'])) {
        $delivery_extras_json = json_encode($input['delivery_extras']);
    }
    
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([
        $order_id, 
        $user_id, 
        $customer_name, 
        $customer_phone,
        $product_summary, 
        $amount, 
        $delivery_fee, 
        $card_surcharge,
        $amount,
        'pending',
        $payment_status,
        $payment_method,
        $order_status,
        $input['delivery_type'] ?? 'pickup',
        $input['delivery_address'] ?? null,
        $input['pickup_time'] ?? null,
        $input['customer_notes'] ?? null,
        $calculated_subtotal,
        $discount_amount,
        $delivery_discount,
        $delivery_extras_total,
        $delivery_extras_json,
        $cashback_used,
        $input['scheduled_time'] ?? null,
        isset($input['is_scheduled']) ? ($input['is_scheduled'] ? 1 : 0) : 0,
        $input['delivery_distance_km'] ?? null,
        $input['delivery_duration_min'] ?? null
    ]);
    
    $order_db_id = $pdo->lastInsertId();
    
    // Mapa product_id → order_item_id para trazabilidad de inventario
    $order_item_ids = [];
    
    // Guardar items específicos
    foreach ($cart_items as $item) {
        $product_id = $item['id'] ?? null;
        $product_name = $item['name'] ?? 'Producto sin nombre';
        $product_price = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $subtotal = $product_price * $quantity;
        
        // Agregar precio de customizations
        if (!empty($item['customizations']) && is_array($item['customizations'])) {
            foreach ($item['customizations'] as $custom) {
                $subtotal += ($custom['price'] ?? 0) * ($custom['quantity'] ?? 1);
            }
        }
        
        // Detectar si es combo
        $is_combo = isset($item['type']) && $item['type'] === 'combo' || 
                   isset($item['category_name']) && $item['category_name'] === 'Combos' ||
                   isset($item['selections']);
        
        $item_type = $is_combo ? 'combo' : 'product';
        $combo_data = null;
        
        if ($is_combo) {
            $combo_data = json_encode([
                'fixed_items' => $item['fixed_items'] ?? [],
                'selections' => $item['selections'] ?? [],
                'combo_id' => $item['combo_id'] ?? null,
                'customizations' => $item['customizations'] ?? []
            ]);
        } else if (!empty($item['customizations']) && is_array($item['customizations'])) {
            $combo_data = json_encode([
                'customizations' => $item['customizations']
            ]);
        }
        
        // Calcular costo del item
        $item_cost = 0;
        
        if ($is_combo) {
            // COMBO: Sumar costo de fixed_items + selections
            if (!empty($item['fixed_items'])) {
                foreach ($item['fixed_items'] as $fixed) {
                    $fixed_id = $fixed['product_id'] ?? null;
                    if ($fixed_id) {
                        $cost_stmt = $pdo->prepare("
                            SELECT COALESCE(
                                (SELECT SUM(
                                    i.cost_per_unit * pr.quantity * 
                                    CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                                ) FROM product_recipes pr
                                JOIN ingredients i ON pr.ingredient_id = i.id
                                WHERE pr.product_id = ? AND i.is_active = 1),
                                (SELECT cost_price FROM products WHERE id = ?),
                                0
                            ) as item_cost
                        ");
                        $cost_stmt->execute([$fixed_id, $fixed_id]);
                        $cost_row = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                        $item_cost += ($cost_row['item_cost'] ?? 0) * ($fixed['quantity'] ?? 1);
                    }
                }
            }
            
            if (!empty($item['selections'])) {
                foreach ($item['selections'] as $group => $selection) {
                    $selections_array = is_array($selection) && isset($selection[0]) ? $selection : [$selection];
                    foreach ($selections_array as $sel) {
                        $sel_id = is_array($sel) ? ($sel['id'] ?? null) : null;
                        if ($sel_id) {
                            $cost_stmt = $pdo->prepare("
                                SELECT COALESCE(
                                    (SELECT SUM(
                                        i.cost_per_unit * pr.quantity * 
                                        CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                                    ) FROM product_recipes pr
                                    JOIN ingredients i ON pr.ingredient_id = i.id
                                    WHERE pr.product_id = ? AND i.is_active = 1),
                                    (SELECT cost_price FROM products WHERE id = ?),
                                    0
                                ) as item_cost
                            ");
                            $cost_stmt->execute([$sel_id, $sel_id]);
                            $cost_row = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                            $item_cost += $cost_row['item_cost'] ?? 0;
                        }
                    }
                }
            }
        } else {
            // PRODUCTO NORMAL: Calcular desde receta o cost_price
            if ($product_id) {
                $cost_stmt = $pdo->prepare("
                    SELECT COALESCE(
                        (SELECT SUM(
                            i.cost_per_unit * pr.quantity * 
                            CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                        ) FROM product_recipes pr
                        JOIN ingredients i ON pr.ingredient_id = i.id
                        WHERE pr.product_id = ? AND i.is_active = 1),
                        (SELECT cost_price FROM products WHERE id = ?),
                        0
                    ) as item_cost
                ");
                $cost_stmt->execute([$product_id, $product_id]);
                $cost_row = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                $item_cost = $cost_row['item_cost'] ?? 0;
            }
        }
        
        // Agregar costo de personalizaciones
        if (!empty($item['customizations']) && is_array($item['customizations'])) {
            foreach ($item['customizations'] as $custom) {
                $custom_id = $custom['id'] ?? null;
                $custom_qty = $custom['quantity'] ?? 1;
                if ($custom_id) {
                    $cost_stmt = $pdo->prepare("
                        SELECT COALESCE(
                            (SELECT SUM(
                                i.cost_per_unit * pr.quantity * 
                                CASE WHEN pr.unit = 'g' THEN 0.001 ELSE 1 END
                            ) FROM product_recipes pr
                            JOIN ingredients i ON pr.ingredient_id = i.id
                            WHERE pr.product_id = ? AND i.is_active = 1),
                            (SELECT cost_price FROM products WHERE id = ?),
                            0
                        ) as item_cost
                    ");
                    $cost_stmt->execute([$custom_id, $custom_id]);
                    $cost_row = $cost_stmt->fetch(PDO::FETCH_ASSOC);
                    $item_cost += ($cost_row['item_cost'] ?? 0) * $custom_qty;
                }
            }
        }
        
        $item_sql = "INSERT INTO tuu_order_items (
            order_id, order_reference, product_id, item_type, combo_data,
            product_name, product_price, item_cost, quantity, subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $pdo->prepare($item_sql);
        $item_stmt->execute([
            $order_db_id, $order_id, $product_id, $item_type, $combo_data,
            $product_name, $product_price, $item_cost, $quantity, $subtotal
        ]);
        $order_item_ids[$product_id] = $pdo->lastInsertId();
    }
    
    $pdo->commit();

    // Task 5.2: Descontar inventario para crédito (RL6 y R11) DESPUÉS del commit
    // Usa processSaleInventory() centralizado en vez de código inline duplicado
    if (in_array($payment_method, ['rl6_credit', 'r11_credit'])) {
        try {
            error_log("$payment_method - Iniciando descuento de inventario (centralizado) para orden $order_id");
            $inventory_items = buildInventoryItems($cart_items, $order_item_ids);
            $inv_result = processSaleInventory($pdo, $inventory_items, $order_id);
            if (!empty($inv_result['skipped'])) {
                error_log("$payment_method - Inventario ya procesado para orden $order_id, skipped");
            } elseif (!$inv_result['success']) {
                error_log("$payment_method - ERROR inventario para orden $order_id: " . ($inv_result['error'] ?? 'unknown'));
            } else {
                error_log("$payment_method - Inventario procesado exitosamente para orden $order_id");
            }
        } catch (Exception $inv_error) {
            // No re-lanzar: la orden ya fue committed, solo loguear el error de inventario
            error_log("$payment_method - ERROR procesando inventario post-commit: " . $inv_error->getMessage());
        }
    }

    // Notificar mi3 en realtime (WebSocket via Reverb)
    try {
        $ch = curl_init('https://api-mi3.laruta11.cl/api/v1/webhook/venta');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Webhook-Secret: r11-webhook-2026'],
            CURLOPT_POSTFIELDS => json_encode([
                'order_number' => $order_id,
                'monto' => $amount,
                'source' => 'app3',
                'customer_name' => $customer_name,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
        ]);
        curl_exec($ch);
        curl_close($ch);
    } catch (Exception $e) {}
    
    // Generar 1% cashback si es usuario autenticado, pagó Y NO está cancelada
    if ($user_id && $payment_status === 'paid' && $order_status !== 'cancelled') {
        try {
            $subtotal = $calculated_subtotal;
            $cashback = round($subtotal * 0.01);
            
            if ($cashback > 0) {
                // Actualizar wallet
                $wallet_stmt = $pdo->prepare("
                    UPDATE user_wallet 
                    SET balance = balance + ?,
                        total_earned = total_earned + ?
                    WHERE user_id = ?
                ");
                $wallet_stmt->execute([$cashback, $cashback, $user_id]);
                
                // Obtener nuevo balance
                $balance_stmt = $pdo->prepare("SELECT balance FROM user_wallet WHERE user_id = ?");
                $balance_stmt->execute([$user_id]);
                $new_balance = $balance_stmt->fetchColumn();
                
                // Registrar transacción
                $trans_stmt = $pdo->prepare("
                    INSERT INTO wallet_transactions 
                    (user_id, type, amount, description, balance_after)
                    VALUES (?, 'earned', ?, ?, ?)
                ");
                $trans_stmt->execute([$user_id, $cashback, 'Cashback 1% - Orden ' . $order_id, $new_balance]);
            }
        } catch (Exception $cashback_error) {
            error_log("Cashback generation error: " . $cashback_error->getMessage());
        }
    }
    
    $messages = [
        'cash' => 'Orden creada - Confirmar pago en comandas',
        'card' => 'Orden creada - Confirmar pago en comandas',
        'transfer' => 'Orden creada - Confirmar pago en comandas',
        'rl6_credit' => 'Orden pagada con Crédito RL6',
        'r11_credit' => 'Orden pagada con Crédito R11'
    ];
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'order' => [
            'id' => $order_id,
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_notes' => $input['customer_notes'] ?? null,
            'delivery_type' => $input['delivery_type'] ?? 'pickup',
            'delivery_address' => $input['delivery_address'] ?? null,
            'delivery_fee' => $delivery_fee,
            'card_surcharge' => $card_surcharge,
            'subtotal' => $calculated_subtotal,
            'discount_amount' => $discount_amount,
            'delivery_discount' => $delivery_discount,
            'delivery_extras' => $delivery_extras_total,
            'delivery_extras_items' => $input['delivery_extras'] ?? [],
            'cashback_used' => $cashback_used,
            'total' => $amount,
            'items' => $cart_items,
            'scheduled_time' => $input['scheduled_time'] ?? null,
            'is_scheduled' => $input['is_scheduled'] ?? false
        ],
        'payment_method' => $payment_method,
        'payment_status' => $payment_status,
        'message' => $messages[$payment_method] ?? 'Orden creada exitosamente'
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("Create Order Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
