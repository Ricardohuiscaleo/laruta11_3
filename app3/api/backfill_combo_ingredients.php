<?php
// backfill_combo_ingredients.php
// Fix combo orders that only deducted stock_quantity of combo product
// instead of expanding fixed_items + selections into ingredient-level transactions

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) die("Config no encontrado\n");

require_once __DIR__ . '/process_sale_inventory_fn.php';

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'],
    $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Find combo items with ONLY product-level transactions (no ingredient expansion)
$sql = "SELECT oi.order_reference, oi.id as item_id, oi.product_id, oi.quantity, oi.combo_data
FROM tuu_order_items oi
JOIN tuu_orders o ON oi.order_reference = o.order_number
WHERE oi.item_type = 'combo'
AND o.payment_status = 'paid'
AND o.order_status NOT IN ('cancelled','failed')
AND oi.combo_data IS NOT NULL
AND EXISTS (
    SELECT 1 FROM inventory_transactions it 
    WHERE it.order_reference = oi.order_reference 
    AND it.product_id IS NOT NULL AND it.ingredient_id IS NULL
)
AND NOT EXISTS (
    SELECT 1 FROM inventory_transactions it 
    WHERE it.order_reference = oi.order_reference 
    AND it.ingredient_id IS NOT NULL
)
ORDER BY oi.id DESC";

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo "BAD combo items to fix: " . count($rows) . "\n";

$fixed = 0;
$errors = 0;

foreach ($rows as $r) {
    $cd = json_decode($r['combo_data'], true);
    if (!is_array($cd)) {
        echo "  SKIP {$r['order_reference']} - invalid combo_data\n";
        continue;
    }
    
    $qty = (int)$r['quantity'];
    $orderRef = $r['order_reference'];
    
    try {
        // 1. Delete old product-level transactions
        $del = $pdo->prepare("DELETE FROM inventory_transactions WHERE order_reference = ? AND product_id IS NOT NULL AND ingredient_id IS NULL");
        $del->execute([$orderRef]);
        $deleted = $del->rowCount();
        
        // 2. Revert stock_quantity of combo product
        if ($deleted > 0) {
            $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?")->execute([$qty, $r['product_id']]);
        }
        
        // 3. Re-process with ingredient expansion
        $items = [[
            'id' => $r['product_id'],
            'name' => '',
            'cantidad' => $qty,
            'is_combo' => true,
            'combo_id' => $r['product_id'],
            'fixed_items' => $cd['fixed_items'] ?? [],
            'selections' => $cd['selections'] ?? [],
            'customizations' => $cd['customizations'] ?? [],
            'order_item_id' => $r['item_id'],
        ]];
        
        $result = processSaleInventory($pdo, $items, $orderRef);
        
        if ($result['success'] && empty($result['skipped'])) {
            echo "  OK  {$orderRef} (del:{$deleted})\n";
            $fixed++;
        } elseif (!empty($result['skipped'])) {
            echo "  SKIP {$orderRef} - idempotency guard\n";
        } else {
            echo "  ERR {$orderRef}: " . ($result['error'] ?? 'unknown') . "\n";
            $errors++;
        }
    } catch (Exception $e) {
        echo "  ERR {$orderRef}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nCompleted: {$fixed} fixed, {$errors} errors\n";
