<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

$input = json_decode(file_get_contents('php://input'), true);
$orderNumber = $input['order_number'] ?? '';

if (!$orderNumber) {
    echo json_encode(['success' => false, 'error' => 'Número de orden requerido']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    // Eliminar orden y sus items relacionados
    $pdo->beginTransaction();

    // 1. Obtener información de la orden para saber si restaurar inventario
    $order_sql = "SELECT order_number, payment_status FROM tuu_orders WHERE order_number = ?";
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([$orderNumber]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);

    if ($order && $order['payment_status'] === 'paid') {
        restoreInventory($pdo, $order['order_number']);
    }

    // Eliminar items de la orden
    $sql = "DELETE FROM tuu_order_items WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber]);

    // Eliminar orden
    $sql = "DELETE FROM tuu_orders WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$orderNumber]);

    $pdo->commit();

    echo json_encode(['success' => true]);

}
catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function restoreInventory($pdo, $order_number)
{
    // Obtener todas las transacciones de venta de esta orden
    $stmt = $pdo->prepare("
        SELECT ingredient_id, product_id, quantity, unit, previous_stock, new_stock, order_item_id
        FROM inventory_transactions 
        WHERE order_reference = ? AND transaction_type = 'sale'
    ");
    $stmt->execute([$order_number]);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = 0;
    foreach ($transactions as $trans) {
        $restore_qty = abs($trans['quantity']);
        $order_item_id = $trans['order_item_id'];

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
                (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) 
                VALUES ('return', ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $trans['ingredient_id'],
                $restore_qty,
                $trans['unit'],
                $prev_stock,
                $new_stock,
                $order_number,
                $order_item_id
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
                (transaction_type, product_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id) 
                VALUES ('return', ?, ?, 'unit', ?, ?, ?, ?)
            ")->execute([
                $trans['product_id'],
                $restore_qty,
                $prev_stock,
                $new_stock,
                $order_number,
                $order_item_id
            ]);
            $count++;
        }
    }
    return $count;
}
?>