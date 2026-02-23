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
    
    $amount = round($input['amount']);
    $customer_name = trim($input['customer_name']);
    $customer_phone = trim($input['customer_phone']);
    $customer_email = $input['customer_email'] ?? $customer_phone . '@ruta11.cl';
    $user_id = $input['user_id'] ?? null;
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'];
    $payment_method = $input['payment_method'] ?? 'cash'; // cash, card, transfer, rl6_credit
    $order_id = 'T11-' . time() . '-' . rand(1000, 9999);
    
    error_log("Creating order $order_id for $customer_name, payment: $payment_method");
    
    // Crédito RL6 se marca como pagado automáticamente
    $payment_status = ($payment_method === 'rl6_credit') ? 'paid' : 'unpaid';
    // TODAS las órdenes van directo a cocina para que aparezcan en comandas
    $order_status = 'sent_to_kitchen';
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $pdo->beginTransaction();
    
    // Crear descripción de productos
    $product_summary = count($cart_items) . ' productos: ' . 
        implode(', ', array_slice(array_map(function($item) {
            return $item['name'] . ' x' . $item['quantity'];
        }, $cart_items), 0, 3)) . 
        (count($cart_items) > 3 ? '...' : '');
    
    // Guardar orden principal
    $order_sql = "INSERT INTO tuu_orders (
        order_number, user_id, customer_name, customer_phone, 
        product_name, product_price, delivery_fee, installment_amount, 
        has_item_details, status, payment_status, payment_method, order_status, delivery_type, 
        delivery_address, pickup_time, customer_notes, subtotal, discount_amount, delivery_discount, 
        delivery_extras, delivery_extras_items, cashback_used, scheduled_time, is_scheduled
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
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
        $amount,
        'pending',
        $payment_status,
        $payment_method,
        $order_status,
        $input['delivery_type'] ?? 'pickup',
        $input['delivery_address'] ?? null,
        $input['pickup_time'] ?? null,
        $input['customer_notes'] ?? null,
        $input['subtotal'] ?? 0,
        $input['discount_amount'] ?? 0,
        $input['delivery_discount'] ?? 0,
        $input['delivery_extras_total'] ?? 0,
        $delivery_extras_json,
        $input['cashback_used'] ?? 0,
        $input['scheduled_time'] ?? null,
        isset($input['is_scheduled']) ? ($input['is_scheduled'] ? 1 : 0) : 0
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
    
    // Descontar inventario para RL6 Credit ANTES de commit
    if ($payment_method === 'rl6_credit') {
        try {
            error_log("RL6 Credit - Iniciando descuento de inventario para orden $order_id");
            
            // Procesar cada item del carrito
            foreach ($cart_items as $item) {
                $product_id = $item['id'];
                $quantity = $item['quantity'];
                $current_order_item_id = $order_item_ids[$product_id] ?? null;
                
                error_log("RL6 Credit - Procesando producto ID: $product_id, cantidad: $quantity");
                
                // Verificar si tiene receta
                $recipe_stmt = $pdo->prepare("
                    SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name
                    FROM product_recipes pr 
                    JOIN ingredients i ON pr.ingredient_id = i.id 
                    WHERE pr.product_id = ? AND i.is_active = 1
                ");
                $recipe_stmt->execute([$product_id]);
                $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($recipe)) {
                    error_log("RL6 Credit - Producto $product_id tiene receta con " . count($recipe) . " ingredientes");
                    
                    // Descontar ingredientes
                    foreach ($recipe as $ingredient) {
                        $ingredient_quantity = $ingredient['quantity'];
                        if ($ingredient['unit'] === 'g') {
                            $ingredient_quantity = $ingredient_quantity / 1000;
                        }
                        
                        $total_needed = $ingredient_quantity * $quantity;
                        $new_stock = $ingredient['current_stock'] - $total_needed;
                        
                        // Registrar transacción
                        $trans_stmt = $pdo->prepare("
                            INSERT INTO inventory_transactions 
                            (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                            VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $trans_stmt->execute([
                            $ingredient['ingredient_id'],
                            -$total_needed,
                            $ingredient['unit'],
                            $ingredient['current_stock'],
                            $new_stock,
                            $order_id,
                            $current_order_item_id
                        ]);
                        
                        // Actualizar stock
                        $update_stmt = $pdo->prepare("
                            UPDATE ingredients 
                            SET current_stock = ?, updated_at = NOW() 
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$new_stock, $ingredient['ingredient_id']]);
                        
                        error_log("RL6 Credit - Descontado ingrediente {$ingredient['name']}: $total_needed {$ingredient['unit']}");
                    }
                    
                    // Recalcular stock del producto
                    $recalc_stmt = $pdo->prepare("
                        UPDATE products p 
                        SET stock_quantity = (
                            SELECT COALESCE(
                                FLOOR(MIN(
                                    CASE 
                                        WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity
                                        ELSE i.current_stock / pr.quantity
                                    END
                                )), 0
                            )
                            FROM product_recipes pr
                            JOIN ingredients i ON pr.ingredient_id = i.id
                            WHERE pr.product_id = p.id 
                            AND i.is_active = 1
                            AND i.current_stock > 0
                        )
                        WHERE p.id = ?
                    ");
                    $recalc_stmt->execute([$product_id]);
                } else {
                    error_log("RL6 Credit - Producto $product_id SIN receta, descontando stock directo");
                    
                    // Sin receta, descontar producto directo
                    $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
                    $stock_stmt->execute([$product_id]);
                    $current = $stock_stmt->fetch(PDO::FETCH_ASSOC);
                    $prev_stock = $current['stock_quantity'] ?? 0;
                    $new_stock = $prev_stock - $quantity;
                    
                    $trans_stmt = $pdo->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                        VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)
                    ");
                    $trans_stmt->execute([$product_id, -$quantity, $prev_stock, $new_stock, $order_id, $current_order_item_id]);
                    
                    $product_stmt = $pdo->prepare("
                        UPDATE products 
                        SET stock_quantity = stock_quantity - ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $product_stmt->execute([$quantity, $product_id]);
                }
            }
            
            error_log("RL6 Credit - Inventario procesado exitosamente para orden $order_id");
        } catch (Exception $inv_error) {
            error_log("RL6 Credit - ERROR procesando inventario: " . $inv_error->getMessage());
            throw $inv_error; // Re-lanzar para hacer rollback
        }
    }
    
    $pdo->commit();
    
    // Generar 1% cashback si es usuario autenticado, pagó Y NO está cancelada
    if ($user_id && $payment_status === 'paid' && $order_status !== 'cancelled') {
        try {
            $subtotal = $input['subtotal'] ?? $amount;
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
        'rl6_credit' => 'Orden pagada con Crédito RL6'
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
            'subtotal' => $input['subtotal'] ?? 0,
            'discount_amount' => $input['discount_amount'] ?? 0,
            'delivery_discount' => $input['delivery_discount'] ?? 0,
            'delivery_extras' => $input['delivery_extras_total'] ?? 0,
            'delivery_extras_items' => $input['delivery_extras'] ?? [],
            'cashback_used' => $input['cashback_used'] ?? 0,
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
