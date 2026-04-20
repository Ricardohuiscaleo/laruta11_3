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
    
    if (!$input || !isset($input['items'])) {
        throw new Exception('Items de venta requeridos');
    }
    
    $items = $input['items'];
    $order_reference = $input['order_reference'] ?? null;
    $pdo->beginTransaction();
    
    // Función auxiliar para procesar inventario
    function processProductInventory($pdo, $product_id, $quantity_sold, $order_reference = null, $order_item_id = null) {
        // Verificar si la tabla product_recipes existe y el producto tiene receta
        $table_check = $pdo->query("SHOW TABLES LIKE 'product_recipes'");
        $recipe = [];
        
        if ($table_check->rowCount() > 0) {
            $recipe_stmt = $pdo->prepare("
                SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name, i.is_composite
                FROM product_recipes pr 
                JOIN ingredients i ON pr.ingredient_id = i.id 
                WHERE pr.product_id = ? AND i.is_active = 1
            ");
            $recipe_stmt->execute([$product_id]);
            $recipe = $recipe_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        if (!empty($recipe)) {
            // Producto preparado - descontar ingredientes Y producto
            foreach ($recipe as $ingredient) {
                // Resolve composite ingredients to their children
                $deductions = resolveIngredientDeductionPSI(
                    $pdo,
                    (int) $ingredient['ingredient_id'],
                    (float) $ingredient['quantity'] * $quantity_sold,
                    $ingredient['unit'],
                    !empty($ingredient['is_composite'])
                );

                foreach ($deductions as $deduction) {
                    $deduct_qty = $deduction['quantity'];
                    $deduct_unit = $deduction['unit'];
                    if ($deduct_unit === 'g') {
                        $deduct_qty = $deduct_qty / 1000;
                        $deduct_unit = 'kg';
                    }

                    // Get current stock
                    $stock_check = $pdo->prepare("SELECT current_stock, name FROM ingredients WHERE id = ?");
                    $stock_check->execute([$deduction['ingredient_id']]);
                    $stock_data = $stock_check->fetch(PDO::FETCH_ASSOC);
                    $prev_stock = (float) ($stock_data['current_stock'] ?? 0);
                    $new_stock = $prev_stock - $deduct_qty;

                    if ($new_stock < 0) {
                        error_log("Advertencia: Stock negativo de " . ($stock_data['name'] ?? $deduction['ingredient_id']) . ": {$new_stock}");
                    }

                    // Registrar transacción ANTES de actualizar
                    $trans_stmt = $pdo->prepare("
                        INSERT INTO inventory_transactions 
                        (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                        VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $trans_stmt->execute([
                        $deduction['ingredient_id'],
                        -$deduct_qty,
                        $deduct_unit,
                        $prev_stock,
                        $new_stock,
                        $order_reference,
                        $order_item_id
                    ]);

                    // Actualizar stock del ingrediente
                    $update_stmt = $pdo->prepare("
                        UPDATE ingredients 
                        SET current_stock = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$new_stock, $deduction['ingredient_id']]);
                }
            }
            
            // Recalcular stock del producto basado en ingredientes
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
            // Producto simple - descontar stock directo
            // Obtener stock actual primero
            $stock_stmt = $pdo->prepare("SELECT stock_quantity, name FROM products WHERE id = ?");
            $stock_stmt->execute([$product_id]);
            $product_data = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product_data) {
                $prev_stock = $product_data['stock_quantity'];
                $new_stock = $prev_stock - $quantity_sold;
                
                // Registrar transacción ANTES de actualizar
                $trans_stmt = $pdo->prepare("
                    INSERT INTO inventory_transactions 
                    (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                    VALUES ('sale', ?, ?, 'unit', ?, ?, ?, ?)
                ");
                $trans_stmt->execute([
                    $product_id,
                    -$quantity_sold,
                    $prev_stock,
                    $new_stock,
                    $order_reference,
                    $order_item_id
                ]);
                
                // Actualizar stock
                $product_stmt = $pdo->prepare("
                    UPDATE products 
                    SET stock_quantity = stock_quantity - ?, updated_at = NOW() 
                    WHERE id = ? AND stock_quantity >= ?
                ");
                $product_stmt->execute([$quantity_sold, $product_id, $quantity_sold]);
                
                if ($product_stmt->rowCount() === 0) {
                    error_log("Advertencia: Stock insuficiente del producto ID: {$product_id}");
                }
            }
        }
    }
    
    foreach ($items as $item) {
        $order_item_id = $item['order_item_id'] ?? null;
        
        // Verificar si es un combo
        if (isset($item['is_combo']) && $item['is_combo']) {
            $combo_id = $item['combo_id'];
            $quantity_sold = $item['cantidad'];
            
            // Obtener items fijos del combo
            $combo_items_stmt = $pdo->prepare("
                SELECT ci.product_id, ci.quantity
                FROM combo_items ci
                WHERE ci.combo_id = ? AND ci.is_selectable = 0
            ");
            $combo_items_stmt->execute([$combo_id]);
            $combo_items = $combo_items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Procesar cada item del combo
            foreach ($combo_items as $combo_item) {
                $total_quantity = $combo_item['quantity'] * $quantity_sold;
                processProductInventory($pdo, $combo_item['product_id'], $total_quantity, $order_reference, $order_item_id);
            }
            
            // Procesar selecciones del combo
            if (isset($item['selections'])) {
                foreach ($item['selections'] as $selection) {
                    processProductInventory($pdo, $selection['product_id'], $quantity_sold, $order_reference, $order_item_id);
                }
            }
        } else {
            // Producto normal
            $product_id = $item['id'];
            $quantity_sold = $item['cantidad'];
            processProductInventory($pdo, $product_id, $quantity_sold, $order_reference, $order_item_id);
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Inventario actualizado correctamente'
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Resolve a potentially composite ingredient into actual deductions.
 */
function resolveIngredientDeductionPSI($pdo, int $ingredient_id, float $total_quantity, string $unit, bool $is_composite): array
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
