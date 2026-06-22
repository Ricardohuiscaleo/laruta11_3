<?php
header('Content-Type: application/json');

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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        exit;
    }
    
    $order_reference = $input['order_reference'] ?? '';
    $customer_name = $input['customer_name'] ?? '';
    $customer_phone = $input['customer_phone'] ?? '';
    $total_amount = $input['total_amount'] ?? 0;
    $delivery_fee = $input['delivery_fee'] ?? 0;
    $cart_items = $input['cart_items'] ?? [];
    
    if (empty($order_reference) || empty($cart_items)) {
        echo json_encode(['success' => false, 'error' => 'Referencia de pedido y items son requeridos']);
        exit;
    }
    
    $pdo->beginTransaction();
    
    // 1. Crear o actualizar el pedido principal
    $order_sql = "
        INSERT INTO tuu_orders (
            order_reference, 
            customer_name, 
            customer_phone, 
            product_name, 
            product_price,
            delivery_fee,
            has_item_details,
            order_status,
            payment_status, 
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, TRUE, 'pending', 'paid', NOW())
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            customer_phone = VALUES(customer_phone),
            product_name = VALUES(product_name),
            product_price = VALUES(product_price),
            delivery_fee = VALUES(delivery_fee),
            has_item_details = TRUE,
            payment_status = 'paid'
    ";
    
    // Crear descripción de productos para el campo product_name
    $product_summary = count($cart_items) . ' productos: ' . 
        implode(', ', array_slice(array_map(function($item) {
            return $item['name'] . ' x' . $item['quantity'];
        }, $cart_items), 0, 3)) . 
        (count($cart_items) > 3 ? '...' : '');
    
    $stmt = $pdo->prepare($order_sql);
    $stmt->execute([
        $order_reference,
        $customer_name,
        $customer_phone,
        $product_summary,
        $total_amount,
        $delivery_fee
    ]);
    
    $order_id = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM tuu_orders WHERE order_reference = '$order_reference'")->fetchColumn();
    
    // 2. Limpiar items existentes (en caso de actualización)
    $delete_sql = "DELETE FROM tuu_order_items WHERE order_reference = ?";
    $stmt = $pdo->prepare($delete_sql);
    $stmt->execute([$order_reference]);
    
    // 3. Insertar items del carrito con item_type y combo_data
    $item_sql = "
        INSERT INTO tuu_order_items (
            order_id, 
            order_reference, 
            product_id, 
            item_type,
            combo_data,
            product_name, 
            product_price, 
            quantity, 
            subtotal
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $pdo->prepare($item_sql);
    $order_item_ids = [];
    
    foreach ($cart_items as $item) {
        $product_id = $item['id'] ?? null;
        $product_name = $item['name'] ?? 'Producto sin nombre';
        $product_price = $item['price'] ?? 0;
        $quantity = $item['quantity'] ?? 1;
        $subtotal = $product_price * $quantity;
        
        $is_combo = (isset($item['type']) && $item['type'] === 'combo') ||
                    (isset($item['category_name']) && $item['category_name'] === 'Combos') ||
                    !empty($item['selections']);
        $item_type = $is_combo ? 'combo' : 'product';
        $combo_data = null;
        
        if ($is_combo) {
            $combo_data = json_encode([
                'fixed_items' => $item['fixed_items'] ?? [],
                'selections' => $item['selections'] ?? [],
                'combo_id' => $item['combo_id'] ?? null,
                'customizations' => $item['customizations'] ?? [],
            ]);
        } else if (!empty($item['customizations'])) {
            $combo_data = json_encode([
                'customizations' => $item['customizations']
            ]);
        }
        
        $stmt->execute([
            $order_id,
            $order_reference,
            $product_id,
            $item_type,
            $combo_data,
            $product_name,
            $product_price,
            $quantity,
            $subtotal
        ]);
        $order_item_ids[] = $pdo->lastInsertId();
    }
    
    $pdo->commit();
    
    // 4. Procesar inventario después del commit
    try {
        $items_stmt = $pdo->prepare("SELECT id, product_id, product_name, item_type, combo_data, quantity FROM tuu_order_items WHERE order_reference = ?");
        $items_stmt->execute([$order_reference]);
        $saved_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($saved_items)) {
            foreach ($saved_items as $si) {
                $is_combo = ($si['item_type'] ?? '') === 'combo';
                if ($is_combo && $si['combo_data']) {
                    $cd = json_decode($si['combo_data'], true);
                    foreach (($cd['fixed_items'] ?? []) as $fixed) {
                        $pid = $fixed['product_id'] ?? null;
                        $qty = ($fixed['quantity'] ?? 1) * $si['quantity'];
                        if ($pid) deductProductInline($pdo, $pid, $qty, $order_reference, $si['id']);
                    }
                    foreach (($cd['selections'] ?? []) as $group) {
                        $selections = is_array($group) && isset($group[0]) ? $group : [$group];
                        foreach ($selections as $sel) {
                            $sid = is_array($sel) ? ($sel['id'] ?? null) : null;
                            if ($sid) deductProductInline($pdo, $sid, $si['quantity'], $order_reference, $si['id']);
                        }
                    }
                } else if ($si['product_id']) {
                    deductProductInline($pdo, $si['product_id'], $si['quantity'], $order_reference, $si['id']);
                }
            }
            error_log("caja3 save_order_with_items - Inventario procesado exitosamente para $order_reference");
        }
    } catch (Exception $inv_error) {
        error_log("caja3 save_order_with_items - ERROR procesando inventario: " . $inv_error->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pedido guardado con detalle de productos',
        'order_id' => $order_id,
        'order_reference' => $order_reference,
        'items_count' => count($cart_items)
    ]);
    
} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function deductProductInline($pdo, $product_id, $quantity, $order_reference, $order_item_id = null) {
    $recipe = $pdo->prepare("SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name FROM product_recipes pr JOIN ingredients i ON pr.ingredient_id = i.id WHERE pr.product_id = ? AND i.is_active = 1");
    $recipe->execute([$product_id]);
    $rows = $recipe->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($rows)) {
        foreach ($rows as $r) {
            $dq = $r['quantity'] * $quantity;
            $unit = $r['unit'];
            if ($unit === 'g') { $dq = $dq / 1000; $unit = 'kg'; }
            $prev = (float)$r['current_stock'];
            $new = $prev - $dq;
            $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$r['ingredient_id'], -$dq, $unit, $prev, $new, $order_reference, $order_item_id]);
            $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")->execute([$new, $r['ingredient_id']]);
        }
        $pdo->prepare("UPDATE products p SET stock_quantity = (SELECT COALESCE(FLOOR(MIN(CASE WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity ELSE i.current_stock / pr.quantity END)), 0) FROM product_recipes pr JOIN ingredients i ON pr.ingredient_id = i.id WHERE pr.product_id = p.id AND i.is_active = 1 AND i.current_stock > 0) WHERE p.id = ?")->execute([$product_id]);
    }
}
