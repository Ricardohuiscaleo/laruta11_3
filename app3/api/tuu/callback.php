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

    // Determinar payment_status basado en el resultado
    $payment_status = match($result) {
        'completed' => 'paid',
        'failed', 'cancelled' => 'unpaid',
        default => 'unpaid'
    };

    // Determinar order_status basado en el resultado
    $order_status = match($result) {
        'completed' => 'delivered',
        'failed', 'cancelled' => 'cancelled',
        default => 'pending'
    };

    $sql = "UPDATE tuu_orders SET 
            status = ?, 
            payment_status = ?,
            order_status = ?,
            tuu_transaction_id = ?,
            tuu_message = ?,
            tuu_amount = ?,
            tuu_timestamp = NOW(),
            updated_at = NOW()
            WHERE order_number = ?";
    
    $tuu_message = ($result === 'completed') ? 'Transaccion aprobada' : "Transaccion $result";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $payment_status, $order_status, $transaction_id, $tuu_message, $amount, $order_id]);

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

    // NUEVO: Procesar inventario si el pago fue exitoso
    if ($new_status === 'completed') {
        try {
            // Obtener items de la orden
            $items_stmt = $pdo->prepare("
                SELECT oi.id as order_item_id, oi.product_id, oi.product_name, oi.quantity, 
                       oi.item_type, oi.combo_data
                FROM tuu_order_items oi
                WHERE oi.order_reference = ?
            ");
            $items_stmt->execute([$order_id]);
            $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($order_items)) {
                // Preparar datos para process_sale_inventory.php
                $inventory_items = [];
                foreach ($order_items as $item) {
                    $inventory_item = [
                        'id' => $item['product_id'],
                        'name' => $item['product_name'],
                        'cantidad' => $item['quantity'],
                        'order_item_id' => $item['order_item_id']
                    ];
                    
                    // Soporte para combos
                    if ($item['item_type'] === 'combo' && $item['combo_data']) {
                        $combo_data = json_decode($item['combo_data'], true);
                        $inventory_item['is_combo'] = true;
                        $inventory_item['combo_id'] = $combo_data['combo_id'] ?? null;
                        $inventory_item['fixed_items'] = $combo_data['fixed_items'] ?? [];
                        $inventory_item['selections'] = $combo_data['selections'] ?? [];
                    }
                    
                    // Soporte para customizations
                    if ($item['item_type'] === 'product' && $item['combo_data']) {
                        $combo_data = json_decode($item['combo_data'], true);
                        if (isset($combo_data['customizations'])) {
                            $inventory_item['customizations'] = $combo_data['customizations'];
                        }
                    }
                    
                    $inventory_items[] = $inventory_item;
                }
                
                // Llamar a process_sale_inventory.php
                $inventory_data = [
                    'items' => $inventory_items,
                    'order_reference' => $order_id
                ];
                
                $ch = curl_init('https://app.laruta11.cl/api/process_sale_inventory.php');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($inventory_data));
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                
                $inventory_response = curl_exec($ch);
                $inventory_result = json_decode($inventory_response, true);
                curl_close($ch);
                
                if (!$inventory_result || !$inventory_result['success']) {
                    error_log("TUU Callback - Error procesando inventario para orden $order_id: " . 
                             ($inventory_result['error'] ?? 'Unknown error'));
                } else {
                    error_log("TUU Callback - Inventario procesado exitosamente para orden $order_id");
                }
            }
        } catch (Exception $inv_error) {
            error_log("TUU Callback - Exception procesando inventario: " . $inv_error->getMessage());
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
?>