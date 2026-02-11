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
    
    // Log para debug
    error_log('Processing inventory for items: ' . json_encode($items));
    $pdo->beginTransaction();
    
    // Función auxiliar para procesar inventario
    function processProductInventory($pdo, $product_id, $quantity_sold, $order_reference = null, $order_item_id = null) {
        // Verificar si la tabla product_recipes existe y el producto tiene receta
        $table_check = $pdo->query("SHOW TABLES LIKE 'product_recipes'");
        $recipe = [];
        
        if ($table_check->rowCount() > 0) {
            $recipe_stmt = $pdo->prepare("
                SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock, i.name
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
                // Convertir gramos a kg si es necesario
                $ingredient_quantity = $ingredient['quantity'];
                if ($ingredient['unit'] === 'g') {
                    $ingredient_quantity = $ingredient_quantity / 1000; // convertir g a kg
                }
                
                $total_needed = $ingredient_quantity * $quantity_sold;
                $new_stock = $ingredient['current_stock'] - $total_needed;
                
                if ($new_stock < 0) {
                    // Solo advertencia, no bloquear venta
                    error_log("Advertencia: Stock negativo de {$ingredient['name']}: {$new_stock}");
                }
                
                // Registrar transacción ANTES de actualizar
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
                    $order_reference,
                    $order_item_id
                ]);
                
                // Actualizar stock del ingrediente
                $update_stmt = $pdo->prepare("
                    UPDATE ingredients 
                    SET current_stock = ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$new_stock, $ingredient['ingredient_id']]);
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
            error_log("Product $product_id NO RECIPE - Deducting product stock directly");
            // Producto simple - descontar stock directo
            // Obtener stock actual primero
            $stock_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $stock_stmt->execute([$product_id]);
            $current = $stock_stmt->fetch(PDO::FETCH_ASSOC);
            $prev_stock = $current['stock_quantity'] ?? 0;
            $new_stock = $prev_stock - $quantity_sold;
            
            // Registrar transacción
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
            $result = $product_stmt->execute([$quantity_sold, $product_id, $quantity_sold]);
            
            if ($product_stmt->rowCount() === 0) {
                error_log("Advertencia: Stock insuficiente del producto ID: {$product_id}");
            }
        }
    }
    
    $order_reference = $input['order_reference'] ?? null;
    
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
            
            // Procesar personalizaciones si existen
            if (isset($item['customizations']) && is_array($item['customizations'])) {
                foreach ($item['customizations'] as $customization) {
                    $custom_product_id = $customization['id'];
                    $custom_quantity = $customization['quantity'];
                    processProductInventory($pdo, $custom_product_id, $custom_quantity, $order_reference, $order_item_id);
                }
            }
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
?>