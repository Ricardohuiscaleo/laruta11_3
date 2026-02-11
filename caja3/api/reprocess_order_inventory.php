<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
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

function processInventoryDeduction($pdo, $order_items, $order_reference = null) {
    foreach ($order_items as $item) {
        $item_id = $item['id'] ?? null;
        if (!empty($item['combo_data'])) {
            $combo_data = json_decode($item['combo_data'], true);
            if (!empty($combo_data['customizations'])) {
                foreach ($combo_data['customizations'] as $custom) {
                    $custom_id = $custom['id'] ?? null;
                    $custom_qty = ($custom['quantity'] ?? 1) * $item['quantity'];
                    if ($custom_id) {
                        deductProduct($pdo, $custom_id, $custom_qty, $order_reference, $item_id);
                    }
                }
            }
        }
    }
    
    foreach ($order_items as $item) {
        $item_id = $item['id'] ?? null;
        $quantity = $item['quantity'] ?? 1;
        $is_combo = ($item['item_type'] ?? '') === 'combo';
        
        if ($is_combo && !empty($item['combo_data'])) {
            $combo_data = json_decode($item['combo_data'], true);
            
            if (!empty($combo_data['fixed_items'])) {
                foreach ($combo_data['fixed_items'] as $fixed) {
                    $fixed_product_id = $fixed['product_id'] ?? null;
                    $fixed_qty = ($fixed['quantity'] ?? 1) * $quantity;
                    if ($fixed_product_id) {
                        deductProduct($pdo, $fixed_product_id, $fixed_qty, $order_reference, $item_id);
                    }
                }
            }
            
            if (!empty($combo_data['selections'])) {
                foreach ($combo_data['selections'] as $group => $selection) {
                    if (is_array($selection) && isset($selection[0])) {
                        foreach ($selection as $sel) {
                            $sel_id = is_array($sel) ? ($sel['id'] ?? null) : null;
                            if ($sel_id) {
                                deductProduct($pdo, $sel_id, $quantity, $order_reference, $item_id);
                            }
                        }
                    } else if (is_array($selection) && isset($selection['id'])) {
                        deductProduct($pdo, $selection['id'], $quantity, $order_reference, $item_id);
                    }
                }
            }
        } else {
            $product_id = $item['product_id'] ?? null;
            if ($product_id) {
                deductProduct($pdo, $product_id, $quantity, $order_reference, $item_id);
            }
        }
    }
}

function deductProduct($pdo, $product_id, $quantity, $order_reference = null, $order_item_id = null) {
    $recipe_stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock
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
            
            $prev_stock = $ingredient['current_stock'];
            $new_stock = $prev_stock - $deduct_qty;
            
            $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ingredient['ingredient_id'], -$deduct_qty, $ingredient['unit'], $prev_stock, $new_stock, $order_reference, $order_item_id]);
            
            $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $ingredient['ingredient_id']]);
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
        $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stock_stmt->execute([$product_id]);
        $stock_row = $stock_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($stock_row) {
            $prev_stock = $stock_row['stock_quantity'];
            $new_stock = $prev_stock - $quantity;
            
            $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)")
                ->execute([$product_id, -$quantity, $prev_stock, $new_stock, $order_reference, $order_item_id]);
            
            $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $product_id]);
        }
    }
}

try {
    $order_number = $_GET['order_number'] ?? null;
    
    if (!$order_number) {
        throw new Exception('order_number requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $order_stmt = $pdo->prepare("SELECT id, order_number, payment_status FROM tuu_orders WHERE order_number = ?");
    $order_stmt->execute([$order_number]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Orden no encontrada');
    }
    
    $trans_check = $pdo->prepare("SELECT COUNT(*) as count FROM inventory_transactions WHERE order_reference = ?");
    $trans_check->execute([$order_number]);
    $existing = $trans_check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing['count'] > 0) {
        throw new Exception('Esta orden ya tiene transacciones de inventario registradas');
    }
    
    $items_stmt = $pdo->prepare("SELECT id, product_id, item_type, combo_data, quantity FROM tuu_order_items WHERE order_id = ?");
    $items_stmt->execute([$order['id']]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        throw new Exception('No se encontraron items para esta orden');
    }
    
    $pdo->beginTransaction();
    
    processInventoryDeduction($pdo, $items, $order_number);
    
    if ($order['payment_status'] === 'unpaid') {
        $pdo->prepare("UPDATE tuu_orders SET payment_status = 'paid' WHERE id = ?")->execute([$order['id']]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Inventario re-procesado exitosamente',
        'order_number' => $order_number
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
