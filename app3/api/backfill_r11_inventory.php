<?php
// backfill_r11_inventory.php
// Ejecutar UNA VEZ para generar transacciones de inventario de órdenes R11- sin inventario

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
if (!$config) die("Config no encontrado\n");

require_once __DIR__ . '/process_sale_inventory_fn.php';

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'],
    $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Obtener órdenes R11- pagadas sin transacciones de inventario
$orders_stmt = $pdo->query("
    SELECT o.id, o.order_number
    FROM tuu_orders o
    WHERE o.order_number LIKE 'R11-%'
    AND o.payment_status = 'paid'
    AND o.order_status NOT IN ('cancelled', 'failed')
    AND NOT EXISTS (
        SELECT 1 FROM inventory_transactions it WHERE it.order_reference = o.order_number
    )
    ORDER BY o.created_at ASC
");
$orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Procesando " . count($orders) . " órdenes...\n";

$ok = 0; $errors = 0;

foreach ($orders as $order) {
    $items_stmt = $pdo->prepare("
        SELECT oi.id as order_item_id, oi.product_id, oi.product_name, oi.quantity, oi.item_type, oi.combo_data
        FROM tuu_order_items oi
        WHERE oi.order_reference = ?
    ");
    $items_stmt->execute([$order['order_number']]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order_items)) {
        echo "  SKIP {$order['order_number']} - sin items\n";
        continue;
    }

    $inventory_items = [];
    foreach ($order_items as $item) {
        $inv_item = [
            'id'            => $item['product_id'],
            'name'          => $item['product_name'],
            'cantidad'      => $item['quantity'],
            'order_item_id' => $item['order_item_id'],
        ];

        if ($item['item_type'] === 'combo' && $item['combo_data']) {
            $combo_data = json_decode($item['combo_data'], true);
            $inv_item['is_combo']    = true;
            $inv_item['combo_id']    = $item['product_id']; // product_id es el combo_id real
            $inv_item['fixed_items'] = $combo_data['fixed_items'] ?? [];
            $inv_item['selections']  = $combo_data['selections'] ?? [];
        } elseif ($item['item_type'] === 'product' && $item['combo_data']) {
            $combo_data = json_decode($item['combo_data'], true);
            if (!empty($combo_data['customizations'])) {
                $inv_item['customizations'] = $combo_data['customizations'];
            }
        }

        $inventory_items[] = $inv_item;
    }

    $result = processSaleInventory($pdo, $inventory_items, $order['order_number']);

    if ($result['success']) {
        echo "  OK  {$order['order_number']}\n";
        $ok++;
    } else {
        echo "  ERR {$order['order_number']}: {$result['error']}\n";
        $errors++;
    }
}

echo "\nCompletado: $ok OK, $errors errores\n";
?>
