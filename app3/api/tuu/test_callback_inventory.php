<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ── 1. LISTAR órdenes Webpay sin consumo de inventario ──────────────────────
if ($action === 'list') {
    $stmt = $pdo->query("
        SELECT o.id, o.order_number, o.customer_name, o.payment_status,
               o.installment_amount, o.created_at,
               COUNT(oi.id) as item_count,
               COUNT(it.id) as trans_count
        FROM tuu_orders o
        LEFT JOIN tuu_order_items oi ON oi.order_reference = o.order_number
        LEFT JOIN inventory_transactions it ON it.order_reference = o.order_number
        WHERE o.order_number LIKE 'R11-%'
          AND o.payment_status = 'paid'
          AND o.order_number NOT LIKE 'RL6-%'
        GROUP BY o.id
        HAVING trans_count = 0
        ORDER BY o.created_at DESC
        LIMIT 100
    ");
    echo json_encode(['success' => true, 'orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

// ── 2. SIMULAR callback (test con orden real) ────────────────────────────────
if ($action === 'simulate') {
    $order_number = $_GET['order_number'] ?? null;
    if (!$order_number) { echo json_encode(['success' => false, 'error' => 'order_number requerido']); exit; }

    // Verificar que existe
    $check = $pdo->prepare("SELECT id, payment_status FROM tuu_orders WHERE order_number = ?");
    $check->execute([$order_number]);
    $order = $check->fetch(PDO::FETCH_ASSOC);
    if (!$order) { echo json_encode(['success' => false, 'error' => 'Orden no encontrada']); exit; }

    // Obtener items
    $items_stmt = $pdo->prepare("SELECT id, product_id, product_name, quantity, item_type, combo_data FROM tuu_order_items WHERE order_reference = ?");
    $items_stmt->execute([$order_number]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        echo json_encode(['success' => false, 'error' => 'Orden sin items en tuu_order_items']);
        exit;
    }

    // Preparar inventory_items igual que callback_simple
    $inventory_items = [];
    foreach ($order_items as $item) {
        if ($item['item_type'] === 'combo' && $item['combo_data']) {
            $combo_data = json_decode($item['combo_data'], true);
            $inventory_items[] = [
                'order_item_id' => $item['id'],
                'id'            => $item['product_id'],
                'name'          => $item['product_name'],
                'cantidad'      => $item['quantity'],
                'is_combo'      => true,
                'combo_id'      => $combo_data['combo_id'] ?? null,
                'fixed_items'   => $combo_data['fixed_items'] ?? [],
                'selections'    => $combo_data['selections'] ?? []
            ];
        } else {
            $inv_item = [
                'order_item_id' => $item['id'],
                'id'            => $item['product_id'],
                'name'          => $item['product_name'],
                'cantidad'      => $item['quantity']
            ];
            if ($item['combo_data']) {
                $cd = json_decode($item['combo_data'], true);
                if (isset($cd['customizations'])) $inv_item['customizations'] = $cd['customizations'];
            }
            $inventory_items[] = $inv_item;
        }
    }

    require_once __DIR__ . '/../process_sale_inventory_fn.php';
    $inv_result = processSaleInventory($pdo, $inventory_items, $order_number);

    // Leer transacciones generadas
    $trans_stmt = $pdo->prepare("
        SELECT it.*, COALESCE(i.name, p.name) as item_name
        FROM inventory_transactions it
        LEFT JOIN ingredients i ON it.ingredient_id = i.id
        LEFT JOIN products p ON it.product_id = p.id
        WHERE it.order_reference = ?
        ORDER BY it.id ASC
    ");
    $trans_stmt->execute([$order_number]);
    $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'      => $inv_result['success'],
        'error'        => $inv_result['error'] ?? null,
        'order_number' => $order_number,
        'items_count'  => count($inventory_items),
        'transactions' => $transactions
    ]);
    exit;
}

// ── 3. REVERTIR consumo de una orden (para re-testear) ───────────────────────
if ($action === 'revert') {
    $order_number = $_GET['order_number'] ?? null;
    if (!$order_number) { echo json_encode(['success' => false, 'error' => 'order_number requerido']); exit; }

    // Obtener transacciones a revertir
    $trans_stmt = $pdo->prepare("SELECT * FROM inventory_transactions WHERE order_reference = ?");
    $trans_stmt->execute([$order_number]);
    $transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($transactions)) {
        echo json_encode(['success' => false, 'error' => 'No hay transacciones para revertir']);
        exit;
    }

    $pdo->beginTransaction();
    try {
        foreach ($transactions as $t) {
            if ($t['ingredient_id']) {
                // Restaurar stock ingrediente al previous_stock
                $pdo->prepare("UPDATE ingredients SET current_stock = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$t['previous_stock'], $t['ingredient_id']]);
            } elseif ($t['product_id']) {
                $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$t['previous_stock'], $t['product_id']]);
            }
        }
        $pdo->prepare("DELETE FROM inventory_transactions WHERE order_reference = ?")
            ->execute([$order_number]);
        $pdo->commit();
        echo json_encode(['success' => true, 'reverted' => count($transactions)]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ── 4. BACKFILL: procesar todas las órdenes sin consumo ──────────────────────
if ($action === 'backfill') {
    $stmt = $pdo->query("
        SELECT o.order_number
        FROM tuu_orders o
        LEFT JOIN inventory_transactions it ON it.order_reference = o.order_number
        WHERE o.order_number LIKE 'R11-%'
          AND o.payment_status = 'paid'
          AND o.order_number NOT LIKE 'RL6-%'
        GROUP BY o.order_number
        HAVING COUNT(it.id) = 0
        ORDER BY o.created_at DESC
        LIMIT 50
    ");
    $orders = $stmt->fetchAll(PDO::FETCH_COLUMN);

    require_once __DIR__ . '/../process_sale_inventory_fn.php';

    $results = [];
    foreach ($orders as $order_number) {
        $items_stmt = $pdo->prepare("SELECT id, product_id, product_name, quantity, item_type, combo_data FROM tuu_order_items WHERE order_reference = ?");
        $items_stmt->execute([$order_number]);
        $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($order_items)) {
            $results[] = ['order' => $order_number, 'status' => 'sin_items'];
            continue;
        }

        $inventory_items = [];
        foreach ($order_items as $item) {
            if ($item['item_type'] === 'combo' && $item['combo_data']) {
                $cd = json_decode($item['combo_data'], true);
                $inventory_items[] = [
                    'order_item_id' => $item['id'], 'id' => $item['product_id'],
                    'name' => $item['product_name'], 'cantidad' => $item['quantity'],
                    'is_combo' => true, 'combo_id' => $cd['combo_id'] ?? null,
                    'fixed_items' => $cd['fixed_items'] ?? [], 'selections' => $cd['selections'] ?? []
                ];
            } else {
                $inv = ['order_item_id' => $item['id'], 'id' => $item['product_id'],
                        'name' => $item['product_name'], 'cantidad' => $item['quantity']];
                if ($item['combo_data']) {
                    $cd = json_decode($item['combo_data'], true);
                    if (isset($cd['customizations'])) $inv['customizations'] = $cd['customizations'];
                }
                $inventory_items[] = $inv;
            }
        }

        $r = processSaleInventory($pdo, $inventory_items, $order_number);
        $results[] = ['order' => $order_number, 'status' => $r['success'] ? 'ok' : 'error', 'error' => $r['error'] ?? null];
    }

    echo json_encode(['success' => true, 'processed' => count($results), 'results' => $results]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Acción no reconocida']);
