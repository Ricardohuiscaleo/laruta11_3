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
    $order_id = $input['order_id'] ?? null;

    if (!$order_id) {
        throw new Exception('ID de orden requerido');
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    // Verificar que la orden existe
    $check_sql = "SELECT order_number, payment_method, payment_status, installment_amount FROM tuu_orders WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$order_id]);
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Orden no encontrada');
    }

    if ($order['payment_status'] === 'paid') {
        throw new Exception('Esta orden ya está pagada');
    }

    $pdo->beginTransaction();

    // Actualizar estado de pago a 'paid'
    $update_sql = "UPDATE tuu_orders SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$order_id]);

    // Obtener items de la orden para descontar inventario
    $items_stmt = $pdo->prepare("SELECT id, product_id, product_name, item_type, combo_data, quantity FROM tuu_order_items WHERE order_id = ?");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Descontar inventario
    processInventoryDeduction($pdo, $order_items, $order['order_number']);

    // Registrar en caja si es efectivo
    if ($order['payment_method'] === 'cash') {
        registerCashIncome($pdo, $order['installment_amount'], $order['order_number']);
    }

    $pdo->commit();

    $payment_type = $order['payment_method'] === 'card' ? 'tarjeta' :
        ($order['payment_method'] === 'transfer' ? 'transferencia' :
        ($order['payment_method'] === 'cash' ? 'efectivo' : 'PedidosYA'));

    echo json_encode([
        'success' => true,
        'message' => "Pago por {$payment_type} confirmado exitosamente",
        'order_number' => $order['order_number'],
        'payment_method' => $order['payment_method']
    ]);
    exit;

}
catch (Exception $e) {
    if (isset($pdo))
        $pdo->rollBack();
    error_log("Confirm Payment Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}

function registerCashIncome($pdo, $amount, $order_number)
{
    try {
        $saldo_stmt = $pdo->query("SELECT saldo_nuevo FROM caja_movimientos ORDER BY id DESC LIMIT 1");
        $saldo_anterior = 0;
        if ($row = $saldo_stmt->fetch(PDO::FETCH_ASSOC)) {
            $saldo_anterior = floatval($row['saldo_nuevo']);
        }

        $saldo_nuevo = $saldo_anterior + $amount;
        $motivo = "Venta en efectivo - Pedido #{$order_number}";

        $stmt = $pdo->prepare(
            "INSERT INTO caja_movimientos (tipo, monto, motivo, saldo_anterior, saldo_nuevo, usuario, order_reference) 
             VALUES ('ingreso', ?, ?, ?, ?, 'Sistema', ?)"
        );
        $stmt->execute([$amount, $motivo, $saldo_anterior, $saldo_nuevo, $order_number]);
    }
    catch (Exception $e) {
        error_log("Error registering cash income: " . $e->getMessage());
        throw $e;
    }
}

function processInventoryDeduction($pdo, $order_items, $order_reference = null)
{
    error_log("[INVENTORY] Procesando " . count($order_items) . " items para orden: " . $order_reference);

    // Primero procesar customizations si existen
    foreach ($order_items as $item) {
        if (!empty($item['combo_data'])) {
            $combo_data = json_decode($item['combo_data'], true);
            if (!empty($combo_data['customizations'])) {
                foreach ($combo_data['customizations'] as $custom) {
                    $custom_id = $custom['id'] ?? null;
                    $custom_qty = ($custom['quantity'] ?? 1) * $item['quantity'];
                    if ($custom_id) {
                        error_log("[INVENTORY] Descontando customization ID: {$custom_id}, qty: {$custom_qty}");
                        deductProduct($pdo, $custom_id, $custom_qty, $order_reference, $item['id']);
                    }
                }
            }
        }
    }

    // Luego procesar productos principales
    foreach ($order_items as $item) {
        $order_item_id = $item['id'] ?? null;
        $quantity = $item['quantity'] ?? 1;

        // Detección robusta de combo
        $is_combo = (isset($item['item_type']) && $item['item_type'] === 'combo') ||
            (isset($item['category_name']) && $item['category_name'] === 'Combos') ||
            (!empty($item['selections']));

        error_log("[INVENTORY] Item ID: {$order_item_id}, tipo: {$item['item_type']}, qty: {$quantity}");

        if ($is_combo) {
            // COMBO: descontar fixed_items + selections
            $combo_data = !empty($item['combo_data']) ? json_decode($item['combo_data'], true) : [];

            // 1. Descontar fixed_items (v4.3)
            if (!empty($combo_data['fixed_items'])) {
                foreach ($combo_data['fixed_items'] as $fixed) {
                    $fixed_product_id = $fixed['product_id'] ?? null;
                    $fixed_qty = ($fixed['quantity'] ?? 1) * $quantity;
                    if ($fixed_product_id) {
                        error_log("[INVENTORY] Descontando fixed_item ID: {$fixed_product_id}, qty: {$fixed_qty}");
                        deductProduct($pdo, $fixed_product_id, $fixed_qty, $order_reference, $order_item_id);
                    }
                }
            }
            else {
                // Fallback: Descontar receta del combo si no hay fixed_items definidos
                $combo_product_id = $item['product_id'] ?? null;
                if ($combo_product_id) {
                    error_log("[INVENTORY] Fallback: Descontando receta del combo ID: {$combo_product_id}, qty: {$quantity}");
                    deductProduct($pdo, $combo_product_id, $quantity, $order_reference, $order_item_id);
                }
            }

            // 2. Descontar selections (bebidas)
            if (!empty($combo_data['selections'])) {
                foreach ($combo_data['selections'] as $group => $selection) {
                    if (is_array($selection) && isset($selection[0])) {
                        foreach ($selection as $sel) {
                            $sel_id = is_array($sel) ? ($sel['id'] ?? null) : null;
                            if ($sel_id) {
                                error_log("[INVENTORY] Descontando selection ID: {$sel_id}, qty: {$quantity}");
                                deductProduct($pdo, $sel_id, $quantity, $order_reference, $order_item_id);
                            }
                        }
                    }
                    else if (is_array($selection) && isset($selection['id'])) {
                        error_log("[INVENTORY] Descontando selection ID: {$selection['id']}, qty: {$quantity}");
                        deductProduct($pdo, $selection['id'], $quantity, $order_reference, $order_item_id);
                    }
                }
            }
        }
        else {
            // PRODUCTO NORMAL
            $product_id = $item['product_id'] ?? null;
            if ($product_id) {
                error_log("[INVENTORY] Descontando producto normal ID: {$product_id}, qty: {$quantity}");
                deductProduct($pdo, $product_id, $quantity, $order_reference, $order_item_id);
            }
        }
    }
}

function deductProduct($pdo, $product_id, $quantity, $order_reference = null, $order_item_id = null)
{
    // Verificar si tiene receta
    $recipe_stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock
        FROM product_recipes pr 
        JOIN ingredients i ON pr.ingredient_id = i.id 
        WHERE pr.product_id = ? AND i.is_active = 1
    ");
    $recipe_stmt->execute([$product_id]);
    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recipe)) {
        // Producto con receta: descontar ingredientes
        foreach ($recipe as $ingredient) {
            $deduct_qty = $ingredient['quantity'] * $quantity;
            if ($ingredient['unit'] === 'g') {
                $deduct_qty = $deduct_qty / 1000;
            }

            $prev_stock = $ingredient['current_stock'];
            $new_stock = $prev_stock - $deduct_qty;

            // Registrar transacción
            $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$ingredient['ingredient_id'], -$deduct_qty, $ingredient['unit'], $prev_stock, $new_stock, $order_reference, $order_item_id]);

            // Actualizar stock
            $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $ingredient['ingredient_id']]);
        }

        // Recalcular stock del producto
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
    }
    else {
        // Producto sin receta: descontar stock directo
        $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $stock_stmt->execute([$product_id]);
        $stock_row = $stock_stmt->fetch(PDO::FETCH_ASSOC);

        if ($stock_row) {
            $prev_stock = $stock_row['stock_quantity'];
            $new_stock = $prev_stock - $quantity;

            // Registrar transacción
            $pdo->prepare("INSERT INTO inventory_transactions (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)")
                ->execute([$product_id, -$quantity, $prev_stock, $new_stock, $order_reference, $order_item_id]);

            // Actualizar stock
            $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $product_id]);
        }
    }
}
?>