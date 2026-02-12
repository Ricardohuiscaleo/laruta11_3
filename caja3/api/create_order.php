<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
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
    $user_id = $input['user_id'] ?? null; // NULL para órdenes sin usuario (caja/POS)
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'] ?? [];
    $payment_method = $input['payment_method'] ?? 'cash';
    $order_id = 'T11-' . time() . '-' . rand(1000, 9999);
    
    // Nuevos campos
    $subtotal = $amount - $delivery_fee;
    $discount_amount = $input['discount_amount'] ?? 0;
    $delivery_discount = $input['delivery_discount'] ?? 0;
    $delivery_extras = $input['delivery_extras'] ?? 0;
    $delivery_extras_items = !empty($input['delivery_extras_items']) ? json_encode($input['delivery_extras_items']) : null;
    $cashback_used = $input['cashback_used'] ?? 0;
    
    // TODOS los pagos requieren confirmación en comandas
    $payment_status = 'unpaid';
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
    
    // Guardar orden principal (construir INSERT dinámicamente según user_id)
    if ($user_id !== null) {
        $order_sql = "INSERT INTO tuu_orders (
            order_number, user_id, customer_name, customer_phone, 
            product_name, product_price, delivery_fee, installment_amount, 
            has_item_details, status, payment_status, payment_method, order_status, delivery_type, 
            delivery_address, pickup_time, customer_notes, 
            subtotal, discount_amount, delivery_discount, delivery_extras, delivery_extras_items, cashback_used
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $order_stmt = $pdo->prepare($order_sql);
        $order_stmt->execute([
            $order_id, $user_id, $customer_name, $customer_phone,
            $product_summary, $amount, $delivery_fee, $amount,
            'pending',
            $payment_status,
            $payment_method,
            $order_status,
            $input['delivery_type'] ?? 'pickup',
            $input['delivery_address'] ?? null,
            $input['pickup_time'] ?? null,
            $input['customer_notes'] ?? null,
            $subtotal,
            $discount_amount,
            $delivery_discount,
            $delivery_extras,
            $delivery_extras_items,
            $cashback_used
        ]);
    } else {
        $order_sql = "INSERT INTO tuu_orders (
            order_number, customer_name, customer_phone, 
            product_name, product_price, delivery_fee, installment_amount, 
            has_item_details, status, payment_status, payment_method, order_status, delivery_type, 
            delivery_address, pickup_time, customer_notes, 
            subtotal, discount_amount, delivery_discount, delivery_extras, delivery_extras_items, cashback_used
        ) VALUES (?, ?, ?, ?, ?, ?, ?, TRUE, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $order_stmt = $pdo->prepare($order_sql);
        $order_stmt->execute([
            $order_id, $customer_name, $customer_phone,
            $product_summary, $amount, $delivery_fee, $amount,
            'pending',
            $payment_status,
            $payment_method,
            $order_status,
            $input['delivery_type'] ?? 'pickup',
            $input['delivery_address'] ?? null,
            $input['pickup_time'] ?? null,
            $input['customer_notes'] ?? null,
            $subtotal,
            $discount_amount,
            $delivery_discount,
            $delivery_extras,
            $delivery_extras_items,
            $cashback_used
        ]);
    }
    
    $order_db_id = $pdo->lastInsertId();
    
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
                'combo_id' => $item['combo_id'] ?? null
            ]);
        } else if (!empty($item['customizations']) && is_array($item['customizations'])) {
            $combo_data = json_encode([
                'customizations' => $item['customizations']
            ]);
        }
        
        // Calcular costo del item (v4.3)
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
    }
    
    $pdo->commit();
    
    // NO descontar inventario ni registrar en caja hasta confirmar pago en comandas
    
    $messages = [
        'cash' => 'Orden creada - Confirmar pago en comandas',
        'card' => 'Orden creada - Confirmar pago en comandas',
        'transfer' => 'Orden creada - Confirmar pago en comandas',
        'pedidosya' => 'Orden creada - Confirmar pago en comandas'
    ];
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'payment_method' => $payment_method,
        'payment_status' => $payment_status,
        'message' => $messages[$payment_method] ?? 'Orden creada exitosamente',
        'cash_registered' => ($payment_method === 'cash' && $payment_status === 'paid')
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) $pdo->rollBack();
    error_log("Create Order Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processInventoryDeduction($pdo, $cart_items) {
    foreach ($cart_items as $item) {
        $quantity = $item['quantity'] ?? 1;
        $is_combo = isset($item['type']) && $item['type'] === 'combo';
        
        // Descontar customizations (extras)
        if (!empty($item['customizations']) && is_array($item['customizations'])) {
            foreach ($item['customizations'] as $custom) {
                $custom_product_id = $custom['id'] ?? null;
                $custom_qty = ($custom['quantity'] ?? 1);
                if ($custom_product_id) {
                    deductProduct($pdo, $custom_product_id, $custom_qty);
                }
            }
        }
        
        if ($is_combo) {
            // COMBO: descontar fixed_items + selections
            if (!empty($item['fixed_items'])) {
                foreach ($item['fixed_items'] as $fixed) {
                    $fixed_product_id = $fixed['product_id'] ?? null;
                    $fixed_qty = ($fixed['quantity'] ?? 1) * $quantity;
                    if ($fixed_product_id) {
                        deductProduct($pdo, $fixed_product_id, $fixed_qty);
                    }
                }
            }
            
            if (!empty($item['selections'])) {
                foreach ($item['selections'] as $group => $selection) {
                    if (is_array($selection) && isset($selection[0])) {
                        foreach ($selection as $sel) {
                            $sel_id = is_array($sel) ? ($sel['id'] ?? null) : null;
                            if ($sel_id) {
                                deductProduct($pdo, $sel_id, $quantity);
                            }
                        }
                    } else if (is_array($selection) && isset($selection['id'])) {
                        deductProduct($pdo, $selection['id'], $quantity);
                    }
                }
            }
        } else {
            // PRODUCTO NORMAL
            $product_id = $item['id'] ?? null;
            if ($product_id) {
                deductProduct($pdo, $product_id, $quantity);
            }
        }
    }
}

function registerCashIncome($pdo, $amount, $order_id) {
    try {
        // Obtener saldo actual
        $saldo_stmt = $pdo->query("SELECT saldo_nuevo FROM caja_movimientos ORDER BY id DESC LIMIT 1");
        $saldo_anterior = 0;
        if ($row = $saldo_stmt->fetch(PDO::FETCH_ASSOC)) {
            $saldo_anterior = floatval($row['saldo_nuevo']);
        }
        
        $saldo_nuevo = $saldo_anterior + $amount;
        $motivo = "Venta en efectivo - Pedido #{$order_id}";
        
        $stmt = $pdo->prepare(
            "INSERT INTO caja_movimientos (tipo, monto, motivo, saldo_anterior, saldo_nuevo, usuario, order_reference) 
             VALUES ('ingreso', ?, ?, ?, ?, 'Sistema', ?)"
        );
        $stmt->execute([$amount, $motivo, $saldo_anterior, $saldo_nuevo, $order_id]);
    } catch (Exception $e) {
        error_log("Error registering cash income: " . $e->getMessage());
    }
}

function deductProduct($pdo, $product_id, $quantity) {
    $recipe_stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit
        FROM product_recipes pr 
        JOIN ingredients i ON pr.ingredient_id = i.id 
        WHERE pr.product_id = ? AND i.is_active = 1
    ");
    $recipe_stmt->execute([$product_id]);
    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($recipe)) {
        foreach ($recipe as $ingredient) {
            $deduct_qty = $ingredient['quantity'] * $quantity;
            if ($ingredient['unit'] === 'g') {
                $deduct_qty = $deduct_qty / 1000;
            }
            $pdo->prepare("UPDATE ingredients SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?")
                ->execute([$deduct_qty, $ingredient['ingredient_id']]);
        }
        
        $pdo->prepare("
            UPDATE products p SET stock_quantity = (
                SELECT COALESCE(FLOOR(MIN(
                    CASE WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity
                    ELSE i.current_stock / pr.quantity END
                )), 0)
                FROM product_recipes pr
                JOIN ingredients i ON pr.ingredient_id = i.id
                WHERE pr.product_id = p.id AND i.is_active = 1 AND i.current_stock > 0
            ) WHERE p.id = ?
        ")->execute([$product_id]);
    } else {
        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ?")
            ->execute([$quantity, $product_id]);
    }
}
?>
