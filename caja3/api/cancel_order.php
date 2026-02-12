<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $orderId = $input['order_id'] ?? null;

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'ID de pedido requerido']);
        exit;
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->beginTransaction();

    // 1. Obtener información del pedido antes de anular
    $orderSql = "SELECT order_number, payment_method, payment_status, installment_amount FROM tuu_orders WHERE id = ?";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception('Pedido no encontrado');
    }

    // 2. Actualizar el estado del pedido a 'cancelled' y payment_status a 'unpaid' si estaba pagado
    if ($order['payment_status'] === 'paid') {
        $sql = "UPDATE tuu_orders SET order_status = 'cancelled', payment_status = 'unpaid', updated_at = NOW() WHERE id = ?";
    } else {
        $sql = "UPDATE tuu_orders SET order_status = 'cancelled', updated_at = NOW() WHERE id = ?";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderId]);

    // 3. Si era transferencia pagada, registrar reembolso pendiente
    // NOTA: Tabla 'refunds' no existe - comentado temporalmente
    /*
    if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'paid') {
        $refundSql = "INSERT INTO refunds (order_id, order_number, amount, status, created_at) 
                      VALUES (?, ?, ?, 'pending', NOW())";
        $refundStmt = $pdo->prepare($refundSql);
        $refundStmt->execute([$orderId, $order['order_number'], $order['installment_amount']]);
    }
    */

    // 4. Devolver inventario si estaba pagado
    $inventoryRestored = false;
    $restoredCount = 0;
    if ($order['payment_status'] === 'paid') {
        $restoredCount = restoreInventory($pdo, $order['order_number']);
        $inventoryRestored = $restoredCount > 0;
    }
    
    $pdo->commit();

    $message = 'Pedido anulado exitosamente';
    if ($inventoryRestored) {
        $message .= ". Inventario restaurado ({$restoredCount} transacciones)";
    }
    // Mensaje de reembolso comentado ya que tabla refunds no existe
    /*
    if ($order['payment_method'] === 'transfer' && $order['payment_status'] === 'paid') {
        $message .= '. Reembolso registrado como pendiente';
    }
    */

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'inventory_restored' => $inventoryRestored,
        'restored_count' => $restoredCount
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function restoreInventory($pdo, $order_number) {
    // Obtener todas las transacciones de venta de esta orden
    $stmt = $pdo->prepare("
        SELECT ingredient_id, product_id, quantity, unit, previous_stock, new_stock
        FROM inventory_transactions 
        WHERE order_reference = ? AND transaction_type = 'sale'
    ");
    $stmt->execute([$order_number]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = 0;
    foreach ($transactions as $trans) {
        $restore_qty = abs($trans['quantity']);
        
        if ($trans['ingredient_id']) {
            // Devolver ingrediente
            $prev_stmt = $pdo->prepare("SELECT current_stock FROM ingredients WHERE id = ?");
            $prev_stmt->execute([$trans['ingredient_id']]);
            $prev_stock = $prev_stmt->fetchColumn();
            $new_stock = $prev_stock + $restore_qty;
            
            $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $trans['ingredient_id']]);
            
            // Registrar transacción de devolución
            $pdo->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference) 
                VALUES ('return', ?, ?, ?, ?, ?, ?)
            ")->execute([
                $trans['ingredient_id'], 
                $restore_qty, 
                $trans['unit'], 
                $prev_stock,
                $new_stock,
                $order_number
            ]);
            $count++;
        } 
        
        if ($trans['product_id']) {
            // Devolver producto
            $prev_stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $prev_stmt->execute([$trans['product_id']]);
            $prev_stock = $prev_stmt->fetchColumn();
            $new_stock = $prev_stock + $restore_qty;
            
            $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$new_stock, $trans['product_id']]);
            
            // Registrar transacción de devolución
            $pdo->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference) 
                VALUES ('return', ?, ?, 'unit', ?, ?, ?)
            ")->execute([
                $trans['product_id'], 
                $restore_qty, 
                $prev_stock,
                $new_stock,
                $order_number
            ]);
            $count++;
        }
    }
    
    return $count;
}
?>