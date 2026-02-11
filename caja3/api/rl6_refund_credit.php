<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if (!$configPath) {
    echo json_encode(['success' => false, 'message' => 'config.php no encontrado']);
    exit;
}

$config = include $configPath;

try {
    $conn = new mysqli(
        $config['app_db_host'],
        $config['app_db_user'],
        $config['app_db_pass'],
        $config['app_db_name']
    );
    $conn->set_charset("utf8mb4");
    
    if ($conn->connect_error) {
        throw new Exception('Error de conexión: ' . $conn->connect_error);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$order_number = $data['order_number'] ?? '';
$admin_id = $data['admin_id'] ?? 0;
$reason = $data['reason'] ?? 'Pedido cancelado';

if (empty($order_number)) {
    echo json_encode(['success' => false, 'message' => 'Número de orden requerido']);
    exit;
}

try {
    $conn->begin_transaction();

    // 1. Obtener datos del pedido
    $stmt = $conn->prepare("
        SELECT id, user_id, installment_amount, payment_method
        FROM tuu_orders 
        WHERE order_number = ? AND LOWER(payment_method) = 'rl6_credit'
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();

    if (!$order) {
        throw new Exception('Pedido no encontrado o no pagado con crédito RL6');
    }

    $user_id = $order['user_id'];
    $monto = $order['installment_amount'];

    // 2. Reintegrar crédito (restar de credito_usado)
    $stmt = $conn->prepare("
        UPDATE usuarios 
        SET credito_usado = credito_usado - ? 
        WHERE id = ? AND es_militar_rl6 = 1
    ");
    $stmt->bind_param("di", $monto, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows === 0) {
        throw new Exception('No se pudo reintegrar el crédito');
    }

    // 3. Registrar transacción de reintegro
    $stmt = $conn->prepare("
        INSERT INTO rl6_credit_transactions 
        (user_id, order_id, amount, type, description) 
        VALUES (?, ?, ?, 'refund', ?)
    ");
    $stmt->bind_param("isds", $user_id, $order_number, $monto, $reason);
    $stmt->execute();

    // 4. Marcar pedido como cancelado y payment_status unpaid
    $stmt = $conn->prepare("
        UPDATE tuu_orders 
        SET order_status = 'cancelled', payment_status = 'unpaid'
        WHERE order_number = ?
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();

    // 5. Restaurar inventario
    $restoredCount = restoreInventory($conn, $order_number);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Crédito reintegrado e inventario restaurado',
        'refunded_amount' => $monto,
        'inventory_restored' => $restoredCount
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    $conn->close();
}

function restoreInventory($conn, $order_number) {
    // Obtener todas las transacciones de venta de esta orden
    $stmt = $conn->prepare("
        SELECT ingredient_id, product_id, quantity, unit, previous_stock, new_stock
        FROM inventory_transactions 
        WHERE order_reference = ? AND transaction_type = 'sale'
    ");
    $stmt->bind_param("s", $order_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    while ($trans = $result->fetch_assoc()) {
        $restore_qty = abs($trans['quantity']);
        
        if ($trans['ingredient_id']) {
            // Devolver ingrediente
            $prev_stmt = $conn->prepare("SELECT current_stock FROM ingredients WHERE id = ?");
            $prev_stmt->bind_param("i", $trans['ingredient_id']);
            $prev_stmt->execute();
            $prev_result = $prev_stmt->get_result();
            $prev_stock = $prev_result->fetch_row()[0];
            $new_stock = $prev_stock + $restore_qty;
            
            $update_stmt = $conn->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("di", $new_stock, $trans['ingredient_id']);
            $update_stmt->execute();
            
            // Registrar transacción de devolución
            $insert_stmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference) 
                VALUES ('refund', ?, ?, ?, ?, ?, ?)
            ");
            $insert_stmt->bind_param("idsdds", 
                $trans['ingredient_id'], 
                $restore_qty, 
                $trans['unit'], 
                $prev_stock,
                $new_stock,
                $order_number
            );
            $insert_stmt->execute();
            $count++;
        } 
        
        if ($trans['product_id']) {
            // Devolver producto
            $prev_stmt = $conn->prepare("SELECT stock_quantity FROM products WHERE id = ?");
            $prev_stmt->bind_param("i", $trans['product_id']);
            $prev_stmt->execute();
            $prev_result = $prev_stmt->get_result();
            $prev_stock = $prev_result->fetch_row()[0];
            $new_stock = $prev_stock + $restore_qty;
            
            $update_stmt = $conn->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?");
            $update_stmt->bind_param("di", $new_stock, $trans['product_id']);
            $update_stmt->execute();
            
            // Registrar transacción de devolución
            $insert_stmt = $conn->prepare("
                INSERT INTO inventory_transactions 
                (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference) 
                VALUES ('refund', ?, ?, 'unit', ?, ?, ?)
            ");
            $insert_stmt->bind_param("iddds", 
                $trans['product_id'], 
                $restore_qty, 
                $prev_stock,
                $new_stock,
                $order_number
            );
            $insert_stmt->execute();
            $count++;
        }
    }
    
    return $count;
}
?>
