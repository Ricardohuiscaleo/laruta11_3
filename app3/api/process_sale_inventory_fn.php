<?php
function processProductInventory($pdo, $product_id, $quantity_sold, $order_reference = null, $order_item_id = null) {
    $recipe_stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name
        FROM product_recipes pr 
        JOIN ingredients i ON pr.ingredient_id = i.id 
        WHERE pr.product_id = ? AND i.is_active = 1
    ");
    $recipe_stmt->execute([$product_id]);
    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recipe)) {
        foreach ($recipe as $ingredient) {
            $ingredient_quantity = $ingredient['unit'] === 'g'
                ? $ingredient['quantity'] / 1000
                : $ingredient['quantity'];
            $total_needed = $ingredient_quantity * $quantity_sold;
            $new_stock = $ingredient['current_stock'] - $total_needed;

            $pdo->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$ingredient['ingredient_id'], -$total_needed, $ingredient['unit'],
                         $ingredient['current_stock'], $new_stock, $order_reference, $order_item_id]);

            $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $ingredient['ingredient_id']]);
        }

        $pdo->prepare("
            UPDATE products p 
            SET stock_quantity = (
                SELECT COALESCE(FLOOR(MIN(
                    CASE WHEN pr.unit = 'g' THEN i.current_stock * 1000 / pr.quantity
                         ELSE i.current_stock / pr.quantity END
                )), 0)
                FROM product_recipes pr
                JOIN ingredients i ON pr.ingredient_id = i.id
                WHERE pr.product_id = p.id AND i.is_active = 1 AND i.current_stock > 0
            )
            WHERE p.id = ?
        ")->execute([$product_id]);
    } else {
        $current = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
        $current->execute([$product_id]);
        $row = $current->fetch(PDO::FETCH_ASSOC);
        $prev_stock = $row['stock_quantity'] ?? 0;
        $new_stock = $prev_stock - $quantity_sold;

        $pdo->prepare("
            INSERT INTO inventory_transactions 
            (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
            VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)
        ")->execute([$product_id, -$quantity_sold, $prev_stock, $new_stock, $order_reference, $order_item_id]);

        $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = NOW() WHERE id = ? AND stock_quantity >= ?")
            ->execute([$quantity_sold, $product_id, $quantity_sold]);
    }
}

function processSaleInventory($pdo, $items, $order_reference) {
    try {
        $pdo->beginTransaction();

        foreach ($items as $item) {
            $order_item_id = $item['order_item_id'] ?? null;

            if (!empty($item['is_combo'])) {
                $combo_id = $item['combo_id'];
                $quantity_sold = $item['cantidad'];

                $combo_items_stmt = $pdo->prepare("
                    SELECT ci.product_id, ci.quantity
                    FROM combo_items ci
                    WHERE ci.combo_id = ? AND ci.is_selectable = 0
                ");
                $combo_items_stmt->execute([$combo_id]);
                $combo_items = $combo_items_stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($combo_items as $combo_item) {
                    processProductInventory($pdo, $combo_item['product_id'],
                        $combo_item['quantity'] * $quantity_sold, $order_reference, $order_item_id);
                }

                if (!empty($item['selections'])) {
                    foreach ($item['selections'] as $selection) {
                        if (!empty($selection['id'])) {
                            processProductInventory($pdo, $selection['id'], $quantity_sold, $order_reference, $order_item_id);
                        }
                    }
                }
            } else {
                processProductInventory($pdo, $item['id'], $item['cantidad'], $order_reference, $order_item_id);

                if (!empty($item['customizations'])) {
                    foreach ($item['customizations'] as $customization) {
                        processProductInventory($pdo, $customization['id'], $customization['quantity'] ?? 1,
                            $order_reference, $order_item_id);
                    }
                }
            }
        }

        $pdo->commit();
        return ['success' => true];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("processSaleInventory error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
