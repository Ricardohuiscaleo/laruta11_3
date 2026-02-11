<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos inválidos');
    }
    
    $customer_name = $input['customer_name'] ?? 'Cliente Caja';
    $customer_phone = $input['customer_phone'] ?? '';
    $customer_notes = $input['customer_notes'] ?? '';
    $product_name = $input['product_name'] ?? '';
    $product_price = $input['product_price'] ?? 0;
    $installment_amount = $input['installment_amount'] ?? 0;
    $status = $input['status'] ?? 'pending';
    $payment_status = $input['payment_status'] ?? 'unpaid';
    $order_status = $input['order_status'] ?? 'pending';
    $delivery_type = $input['delivery_type'] ?? 'pickup';
    $has_item_details = $input['has_item_details'] ?? 1;
    $tuu_idempotency_key = $input['tuu_idempotency_key'] ?? null;
    $items = $input['items'] ?? [];
    
    $sql = "INSERT INTO tuu_orders (
        customer_name, 
        customer_phone, 
        customer_notes,
        product_name, 
        has_item_details,
        product_price, 
        installment_amount,
        tuu_idempotency_key,
        status, 
        payment_status, 
        order_status,
        delivery_type,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $customer_name,
        $customer_phone,
        $customer_notes,
        $product_name,
        $has_item_details,
        $product_price,
        $installment_amount,
        $tuu_idempotency_key,
        $status,
        $payment_status,
        $order_status,
        $delivery_type
    ]);
    
    $order_id = $pdo->lastInsertId();
    $order_number = "CAJA-{$order_id}";
    
    // Actualizar con el número de orden basado en el ID
    $update_sql = "UPDATE tuu_orders SET order_number = ? WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$order_number, $order_id]);
    
    // Guardar items individuales en tuu_order_items
    $item_ids = [];
    if (!empty($items) && is_array($items)) {
        $item_sql = "INSERT INTO tuu_order_items (
            order_id, order_reference, product_id, item_type, product_name, 
            product_price, item_cost, quantity, subtotal, combo_data
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $item_stmt = $pdo->prepare($item_sql);
        
        foreach ($items as $idx => $item) {
            $product_id = $item['id'] ?? null;
            $quantity = $item['cantidad'] ?? 1;
            $subtotal = $item['price'] * $quantity;
            
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
            
            $item_stmt->execute([
                $order_id,
                $order_number,
                $product_id,
                $item_type,
                $item['name'],
                $item['price'],
                $item_cost,
                $quantity,
                $subtotal,
                $combo_data
            ]);
            $item_ids[$idx] = $pdo->lastInsertId();
        }
    }
    
    // Descontar inventario si el pago es inmediato
    if ($payment_status === 'paid' && !empty($items)) {
        processInventoryDeduction($pdo, $items, $order_number, $item_ids);
    }
    
    // Determinar código HTTP según el estado del pago
    if ($payment_status === 'paid') {
        // Pago completado (efectivo)
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Orden completada',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'status' => 'completed'
        ]);
    } else {
        // Orden creada, pendiente de pago (tarjeta)
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Orden creada - Pendiente de pago',
            'order_id' => $order_id,
            'order_number' => $order_number,
            'status' => 'pending_payment'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processInventoryDeduction($pdo, $items, $order_reference, $item_ids) {
    foreach ($items as $idx => $item) {
        $order_item_id = $item_ids[$idx] ?? null;
        $quantity = $item['cantidad'] ?? 1;
        $is_combo = isset($item['type']) && $item['type'] === 'combo';
        
        if ($is_combo) {
            // COMBO: descontar fixed_items + selections
            if (!empty($item['fixed_items'])) {
                foreach ($item['fixed_items'] as $fixed) {
                    $fixed_product_id = $fixed['product_id'] ?? null;
                    $fixed_qty = ($fixed['quantity'] ?? 1) * $quantity;
                    if ($fixed_product_id) {
                        deductProductCaja($pdo, $fixed_product_id, $fixed_qty, $order_reference, $order_item_id);
                    }
                }
            }
            
            if (!empty($item['selections'])) {
                foreach ($item['selections'] as $group => $selection) {
                    if (is_array($selection) && isset($selection[0])) {
                        foreach ($selection as $sel) {
                            $sel_id = is_array($sel) ? ($sel['id'] ?? null) : null;
                            if ($sel_id) {
                                deductProductCaja($pdo, $sel_id, $quantity, $order_reference, $order_item_id);
                            }
                        }
                    } else if (is_array($selection) && isset($selection['id'])) {
                        deductProductCaja($pdo, $selection['id'], $quantity, $order_reference, $order_item_id);
                    }
                }
            }
        } else {
            // PRODUCTO NORMAL
            $product_id = $item['id'] ?? null;
            if ($product_id) {
                deductProductCaja($pdo, $product_id, $quantity, $order_reference, $order_item_id);
            }
        }
    }
}

function deductProductCaja($pdo, $product_id, $quantity, $order_reference = null, $order_item_id = null) {
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
            
            // Obtener stock actual
            $stock_stmt = $pdo->prepare("SELECT current_stock, name FROM ingredients WHERE id = ?");
            $stock_stmt->execute([$ingredient['ingredient_id']]);
            $ing_data = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ing_data) {
                $prev_stock = $ing_data['current_stock'];
                $new_stock = $prev_stock - $deduct_qty;
                
                // Registrar transacción
                $pdo->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                    VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $ingredient['ingredient_id'],
                    -$deduct_qty,
                    $ingredient['unit'],
                    $prev_stock,
                    $new_stock,
                    $order_reference,
                    $order_item_id
                ]);
                
                // Actualizar stock
                $pdo->prepare("UPDATE ingredients SET current_stock = current_stock - ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$deduct_qty, $ingredient['ingredient_id']]);
            }
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
        // Obtener stock actual del producto
        $stock_stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
        $stock_stmt->execute([$product_id]);
        $prod_data = $stock_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($prod_data) {
            $prev_stock = $prod_data['stock_quantity'];
            $new_stock = $prev_stock - $quantity;
            
            // Registrar transacción
            $pdo->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)
            ")->execute([
                $product_id,
                -$quantity,
                $prev_stock,
                $new_stock,
                $order_reference,
                $order_item_id
            ]);
            
            // Actualizar stock
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ?")
                ->execute([$quantity, $product_id]);
        }
    }
}
?>