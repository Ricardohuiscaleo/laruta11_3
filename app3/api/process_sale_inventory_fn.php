<?php
function processProductInventory($pdo, $product_id, $quantity_sold, $order_reference = null, $order_item_id = null) {
    if (!$product_id) return;

    // Verificar que el producto existe
    $exists = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $exists->execute([$product_id]);
    if (!$exists->fetch()) {
        error_log("processProductInventory: product_id=$product_id no existe en products, skipping");
        return;
    }

    $recipe_stmt = $pdo->prepare("
        SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name, i.is_composite
        FROM product_recipes pr 
        JOIN ingredients i ON pr.ingredient_id = i.id 
        WHERE pr.product_id = ? AND i.is_active = 1
    ");
    $recipe_stmt->execute([$product_id]);
    $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($recipe)) {
        foreach ($recipe as $ingredient) {
            // Resolve composite ingredients to their children (leaf-only tracking)
            $is_composite = !empty($ingredient['is_composite'] ?? false);
            $deductions = resolveIngredientDeductionApp(
                $pdo,
                (int) $ingredient['ingredient_id'],
                (float) $ingredient['quantity'] * $quantity_sold,
                $ingredient['unit'],
                $is_composite
            );

            foreach ($deductions as $deduction) {
                $deduct_qty = $deduction['quantity'];
                $deduct_unit = $deduction['unit'];
                if ($deduct_unit === 'g') {
                    $deduct_qty = $deduct_qty / 1000;
                    $deduct_unit = 'kg';
                }

                $stock_stmt = $pdo->prepare("SELECT current_stock FROM ingredients WHERE id = ?");
                $stock_stmt->execute([$deduction['ingredient_id']]);
                $prev_stock = (float) $stock_stmt->fetchColumn();
                $new_stock = $prev_stock - $deduct_qty;

                $pdo->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                    VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$deduction['ingredient_id'], -$deduct_qty, $deduct_unit,
                             $prev_stock, $new_stock, $order_reference, $order_item_id]);

                $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$new_stock, $deduction['ingredient_id']]);
            }
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
        // Guard de idempotencia: si ya existen transactions para esta orden, skip
        $check = $pdo->prepare("SELECT COUNT(*) FROM inventory_transactions WHERE order_reference = ?");
        $check->execute([$order_reference]);
        if ($check->fetchColumn() > 0) {
            return ['success' => true, 'skipped' => true];
        }

        $pdo->beginTransaction();

        foreach ($items as $item) {
            $order_item_id = $item['order_item_id'] ?? null;

            if (!empty($item['is_combo'])) {
                $combo_id = $item['combo_id'];
                $quantity_sold = $item['cantidad'];

                // Usar fixed_items del pedido (combo_data JSON) — fuente de verdad
                // Fallback a combo_items tabla solo si fixed_items no viene en el array
                if (!empty($item['fixed_items'])) {
                    foreach ($item['fixed_items'] as $fixed) {
                        $fixed_pid = $fixed['product_id'] ?? $fixed['id'] ?? null;
                        $fixed_qty = $fixed['quantity'] ?? 1;
                        if ($fixed_pid) {
                            processProductInventory($pdo, $fixed_pid,
                                $fixed_qty * $quantity_sold, $order_reference, $order_item_id);
                        }
                    }
                } else {
                    // Fallback: consultar combo_items tabla
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
                }

                if (!empty($item['selections'])) {
                    foreach ($item['selections'] as $selection) {
                        // selections puede ser un array de items (agrupado por categoría)
                        // o un item individual con 'id'
                        if (is_array($selection) && !isset($selection['id'])) {
                            // Es un grupo de selections (ej: "Bebidas" => [{id:95}, {id:99}])
                            foreach ($selection as $sel) {
                                if (!empty($sel['id'])) {
                                    processProductInventory($pdo, $sel['id'], $quantity_sold, $order_reference, $order_item_id);
                                }
                            }
                        } elseif (!empty($selection['id'])) {
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

/**
 * Resolve a potentially composite ingredient into actual deductions.
 * If the ingredient is composite (is_composite=1), returns its children scaled by quantity.
 * Otherwise returns the ingredient itself.
 */
function resolveIngredientDeductionApp(PDO $pdo, int $ingredient_id, float $total_quantity, string $unit, bool $is_composite): array
{
    if (!$is_composite) {
        return [['ingredient_id' => $ingredient_id, 'quantity' => $total_quantity, 'unit' => $unit]];
    }

    $children_stmt = $pdo->prepare("
        SELECT ir.child_ingredient_id, ir.quantity, ir.unit
        FROM ingredient_recipes ir
        WHERE ir.ingredient_id = ?
    ");
    $children_stmt->execute([$ingredient_id]);
    $children = $children_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($children)) {
        // Fallback: no children found, deduct from parent
        return [['ingredient_id' => $ingredient_id, 'quantity' => $total_quantity, 'unit' => $unit]];
    }

    $result = [];
    foreach ($children as $child) {
        $result[] = [
            'ingredient_id' => (int) $child['child_ingredient_id'],
            'quantity' => (float) $child['quantity'] * $total_quantity,
            'unit' => $child['unit'],
        ];
    }
    return $result;
}
?>
