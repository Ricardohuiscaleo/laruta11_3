<?php
header('Content-Type: application/json');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

try {
    // Obtener datos del callback
    $order_id = $_GET['x_reference'] ?? null;
    $result = $_GET['x_result'] ?? null;
    $transaction_id = $_GET['x_transaction_id'] ?? null;
    $amount = $_GET['x_amount'] ?? null;

    if (!$order_id) {
        throw new Exception('Order ID requerido');
    }

    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Actualizar estado de la orden
    $new_status = match($result) {
        'completed' => 'completed',
        'failed' => 'failed',
        'cancelled' => 'cancelled',
        default => 'pending'
    };

    $sql = "UPDATE tuu_orders SET 
            status = ?, 
            tuu_transaction_id = ?,
            updated_at = NOW()
            WHERE order_number = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $transaction_id, $order_id]);

    // NUEVO: Registrar en tuu_pagos_online si hay user_id
    $user_sql = "SELECT user_id, customer_name, customer_email, customer_phone FROM tuu_orders WHERE order_number = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$order_id]);
    $order_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order_data && $order_data['user_id']) {
        // Verificar si ya existe el registro
        $check_sql = "SELECT id FROM tuu_pagos_online WHERE order_reference = ?";
        $check_stmt = $pdo->prepare($check_sql);
        $check_stmt->execute([$order_id]);
        
        $completed_at = ($new_status === 'completed') ? date('Y-m-d H:i:s') : null;
        
        if ($check_stmt->fetch()) {
            // Actualizar existente
            $update_sql = "UPDATE tuu_pagos_online SET 
                status = ?, tuu_transaction_id = ?, completed_at = ?, updated_at = NOW()
                WHERE order_reference = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$new_status, $transaction_id, $completed_at, $order_id]);
        } else {
            // Insertar nuevo
            $payment_sql = "INSERT INTO tuu_pagos_online (
                user_id, order_reference, amount, payment_method, 
                tuu_transaction_id, status, customer_name, 
                customer_email, customer_phone, completed_at
            ) VALUES (?, ?, ?, 'webpay', ?, ?, ?, ?, ?, ?)";
            
            $payment_stmt = $pdo->prepare($payment_sql);
            $payment_stmt->execute([
                $order_data['user_id'],
                $order_id,
                $amount,
                $transaction_id,
                $new_status,
                $order_data['customer_name'],
                $order_data['customer_email'],
                $order_data['customer_phone'],
                $completed_at
            ]);
        }
        
        error_log("TUU: Pago registrado para usuario ID {$order_data['user_id']} - Orden: $order_id");
    }

    // Descontar inventario si el pago fue exitoso
    if ($new_status === 'completed') {
        $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM tuu_order_items WHERE order_reference = ?");
        $items_stmt->execute([$order_id]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($order_items)) {
            processInventoryDeduction($pdo, $order_items);
        }
    }
    
    // Log del callback
    error_log("TUU Callback - Order: $order_id, Result: $result, Status: $new_status");

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'status' => $new_status,
        'message' => 'Callback procesado'
    ]);

} catch (Exception $e) {
    error_log("TUU Callback Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function processInventoryDeduction($pdo, $order_items) {
    foreach ($order_items as $item) {
        $product_id = $item['product_id'] ?? null;
        $quantity = $item['quantity'] ?? 1;
        
        if (!$product_id) continue;
        
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
            // Producto con receta: descontar ingredientes
            foreach ($recipe as $ingredient) {
                $deduct_qty = $ingredient['quantity'] * $quantity;
                
                // Convertir g a kg si es necesario
                if ($ingredient['unit'] === 'g') {
                    $deduct_qty = $deduct_qty / 1000;
                }
                
                $update_stmt = $pdo->prepare("
                    UPDATE ingredients 
                    SET current_stock = current_stock - ?, updated_at = NOW() 
                    WHERE id = ?
                ");
                $update_stmt->execute([$deduct_qty, $ingredient['ingredient_id']]);
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
            // Producto sin receta: descontar stock directo
            $product_stmt = $pdo->prepare("
                UPDATE products 
                SET stock_quantity = stock_quantity - ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $product_stmt->execute([$quantity, $product_id]);
        }
    }
}
?>