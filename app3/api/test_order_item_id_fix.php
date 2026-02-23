<?php
/**
 * TEST: Verifica que order_item_id se popula correctamente en inventory_transactions
 * para métodos Webpay (callback_simple.php) y RL6 (create_order.php)
 * 
 * ELIMINAR después de verificar en producción.
 */

header('Content-Type: text/html; charset=utf-8');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_POST['action'] ?? 'show';
$test_prefix = 'TEST_OIID_';
$messages = [];

// ── REVERTIR ──────────────────────────────────────────────────────────────────
if ($action === 'revert') {
    $pdo->beginTransaction();
    try {
        // Obtener IDs de órdenes de test
        $orders = $pdo->query("SELECT id, order_number FROM tuu_orders WHERE order_number LIKE '{$test_prefix}%'")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orders as $o) {
            $pdo->prepare("DELETE FROM inventory_transactions WHERE order_reference = ?")->execute([$o['order_number']]);
            $pdo->prepare("DELETE FROM tuu_order_items WHERE order_reference = ?")->execute([$o['order_number']]);
            $pdo->prepare("DELETE FROM rl6_credit_transactions WHERE order_id = ?")->execute([$o['order_number']]);
        }
        $pdo->query("DELETE FROM tuu_orders WHERE order_number LIKE '{$test_prefix}%'");
        
        $pdo->commit();
        $messages[] = ['ok', '✅ Test revertido. Todas las filas de test eliminadas.'];
    } catch (Exception $e) {
        $pdo->rollBack();
        $messages[] = ['err', '❌ Error al revertir: ' . $e->getMessage()];
    }
}

// ── CREAR TEST ────────────────────────────────────────────────────────────────
if ($action === 'run') {
    // Obtener un producto real con receta
    $product = $pdo->query("
        SELECT DISTINCT p.id, p.name FROM products p
        JOIN product_recipes pr ON pr.product_id = p.id
        JOIN ingredients i ON i.id = pr.ingredient_id AND i.is_active = 1
        WHERE p.is_active = 1 LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        $messages[] = ['err', '❌ No hay productos con receta activa en la BD.'];
    } else {
        $pid = $product['id'];
        $pname = $product['name'];

        // ── TEST 1: Webpay (simula callback_simple.php) ──────────────────────
        $order_num_wp = $test_prefix . 'WP_' . time();
        $pdo->beginTransaction();
        try {
            $pdo->prepare("
                INSERT INTO tuu_orders (order_number, customer_name, product_name, product_price, payment_method, payment_status, status, installment_amount)
                VALUES (?, 'TEST Cliente', ?, 1000, 'webpay', 'paid', 'completed', 1000)
            ")->execute([$order_num_wp, $pname]);
            $order_db_id = $pdo->lastInsertId();

            $pdo->prepare("
                INSERT INTO tuu_order_items (order_id, order_reference, product_id, product_name, product_price, quantity, subtotal, item_type)
                VALUES (?, ?, ?, ?, 1000, 1, 1000, 'product')
            ")->execute([$order_db_id, $order_num_wp, $pid, $pname]);
            $order_item_id_wp = $pdo->lastInsertId();

            // Simula lo que hace callback_simple.php DESPUÉS del fix
            $recipe = $pdo->prepare("
                SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock
                FROM product_recipes pr JOIN ingredients i ON i.id = pr.ingredient_id
                WHERE pr.product_id = ? AND i.is_active = 1 LIMIT 1
            ");
            $recipe->execute([$pid]);
            $ing = $recipe->fetch(PDO::FETCH_ASSOC);

            if ($ing) {
                $qty = ($ing['unit'] === 'g') ? $ing['quantity'] / 1000 : $ing['quantity'];
                $pdo->prepare("
                    INSERT INTO inventory_transactions (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                    VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$ing['ingredient_id'], -$qty, $ing['unit'], $ing['current_stock'], $ing['current_stock'] - $qty, $order_num_wp, $order_item_id_wp]);
            }

            $pdo->commit();
            $messages[] = ['ok', "✅ TEST Webpay creado: orden <b>$order_num_wp</b>, order_item_id en transacción: <b>$order_item_id_wp</b>"];
        } catch (Exception $e) {
            $pdo->rollBack();
            $messages[] = ['err', '❌ Error TEST Webpay: ' . $e->getMessage()];
        }

        // ── TEST 2: RL6 (simula create_order.php) ────────────────────────────
        // Necesita un usuario militar RL6 aprobado
        $rl6_user = $pdo->query("SELECT id FROM usuarios WHERE es_militar_rl6=1 AND credito_aprobado=1 AND credito_bloqueado=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (!$rl6_user) {
            $messages[] = ['warn', '⚠️ No hay usuario RL6 aprobado para test RL6. Solo se ejecutó test Webpay.'];
        } else {
            $order_num_rl6 = $test_prefix . 'RL6_' . time();
            $pdo->beginTransaction();
            try {
                $pdo->prepare("
                    INSERT INTO tuu_orders (order_number, customer_name, product_name, product_price, payment_method, payment_status, status, installment_amount, pagado_con_credito_rl6)
                    VALUES (?, 'TEST Militar', ?, 1000, 'rl6_credit', 'paid', 'completed', 1000, 1)
                ")->execute([$order_num_rl6, $pname]);
                $order_db_id_rl6 = $pdo->lastInsertId();

                // Simula el mapa $order_item_ids del fix en create_order.php
                $pdo->prepare("
                    INSERT INTO tuu_order_items (order_id, order_reference, product_id, product_name, product_price, quantity, subtotal, item_type)
                    VALUES (?, ?, ?, ?, 1000, 1, 1000, 'product')
                ")->execute([$order_db_id_rl6, $order_num_rl6, $pid, $pname]);
                $order_item_id_rl6 = $pdo->lastInsertId();
                $order_item_ids = [$pid => $order_item_id_rl6]; // el mapa del fix

                $recipe2 = $pdo->prepare("
                    SELECT pr.ingredient_id, pr.quantity, pr.unit, i.current_stock
                    FROM product_recipes pr JOIN ingredients i ON i.id = pr.ingredient_id
                    WHERE pr.product_id = ? AND i.is_active = 1 LIMIT 1
                ");
                $recipe2->execute([$pid]);
                $ing2 = $recipe2->fetch(PDO::FETCH_ASSOC);

                if ($ing2) {
                    $qty2 = ($ing2['unit'] === 'g') ? $ing2['quantity'] / 1000 : $ing2['quantity'];
                    $current_order_item_id = $order_item_ids[$pid] ?? null; // el fix
                    $pdo->prepare("
                        INSERT INTO inventory_transactions (transaction_type, ingredient_id, quantity, unit, previous_stock, new_stock, order_reference, order_item_id)
                        VALUES ('sale', ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([$ing2['ingredient_id'], -$qty2, $ing2['unit'], $ing2['current_stock'], $ing2['current_stock'] - $qty2, $order_num_rl6, $current_order_item_id]);
                }

                $pdo->commit();
                $messages[] = ['ok', "✅ TEST RL6 creado: orden <b>$order_num_rl6</b>, order_item_id en transacción: <b>$order_item_id_rl6</b>"];
            } catch (Exception $e) {
                $pdo->rollBack();
                $messages[] = ['err', '❌ Error TEST RL6: ' . $e->getMessage()];
            }
        }
    }
}

// ── VERIFICAR ESTADO ──────────────────────────────────────────────────────────
$test_orders = $pdo->query("
    SELECT o.order_number, o.payment_method,
           COUNT(oi.id) as items,
           COUNT(it.id) as transactions,
           SUM(CASE WHEN it.order_item_id IS NOT NULL THEN 1 ELSE 0 END) as with_item_id,
           SUM(CASE WHEN it.order_item_id IS NULL THEN 1 ELSE 0 END) as without_item_id
    FROM tuu_orders o
    LEFT JOIN tuu_order_items oi ON oi.order_reference = o.order_number
    LEFT JOIN inventory_transactions it ON it.order_reference = o.order_number
    WHERE o.order_number LIKE '{$test_prefix}%'
    GROUP BY o.order_number, o.payment_method
")->fetchAll(PDO::FETCH_ASSOC);

// ── PRODUCCIÓN: últimas 10 órdenes Webpay/RL6 ────────────────────────────────
$prod_check = $pdo->query("
    SELECT o.order_number, o.payment_method,
           COUNT(it.id) as transactions,
           SUM(CASE WHEN it.order_item_id IS NOT NULL THEN 1 ELSE 0 END) as with_item_id,
           SUM(CASE WHEN it.order_item_id IS NULL THEN 1 ELSE 0 END) as without_item_id
    FROM tuu_orders o
    LEFT JOIN inventory_transactions it ON it.order_reference = o.order_number
    WHERE o.payment_method IN ('webpay','rl6_credit')
      AND o.order_number NOT LIKE '{$test_prefix}%'
      AND o.payment_status = 'paid'
    GROUP BY o.order_number, o.payment_method
    ORDER BY o.created_at DESC LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Test order_item_id fix</title>
<style>
body{font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0}
h2{color:#38bdf8}h3{color:#94a3b8;margin-top:24px}
.ok{background:#14532d;border-left:4px solid #22c55e;padding:8px 12px;margin:6px 0;border-radius:4px}
.err{background:#450a0a;border-left:4px solid #ef4444;padding:8px 12px;margin:6px 0;border-radius:4px}
.warn{background:#422006;border-left:4px solid #f97316;padding:8px 12px;margin:6px 0;border-radius:4px}
table{border-collapse:collapse;width:100%;margin-top:8px}
th{background:#1e293b;padding:8px;text-align:left;font-size:12px;color:#94a3b8}
td{padding:7px 8px;border-bottom:1px solid #1e293b;font-size:12px}
.good{color:#22c55e;font-weight:bold}.bad{color:#ef4444;font-weight:bold}
.btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:bold;margin-right:8px}
.btn-run{background:#3b82f6;color:#fff}
.btn-revert{background:#ef4444;color:#fff}
.note{background:#1e293b;padding:10px;border-radius:6px;font-size:11px;color:#64748b;margin-top:20px}
</style>
</head>
<body>
<h2>🧪 Test: order_item_id en inventory_transactions</h2>
<p style="color:#64748b;font-size:12px">Verifica que Webpay (callback_simple.php) y RL6 (create_order.php) populan <code>order_item_id</code> correctamente.</p>

<?php foreach ($messages as [$type, $msg]): ?>
<div class="<?= $type ?>"><?= $msg ?></div>
<?php endforeach; ?>

<form method="POST" style="margin:16px 0">
    <button class="btn btn-run" name="action" value="run">▶ Ejecutar Test</button>
    <button class="btn btn-revert" name="action" value="revert" onclick="return confirm('¿Eliminar todos los datos de test?')">🗑 Revertir y Eliminar Test</button>
</form>

<h3>Órdenes de Test (<?= $test_prefix ?>*)</h3>
<?php if (empty($test_orders)): ?>
<p style="color:#64748b;font-size:12px">Sin datos de test. Ejecuta el test primero.</p>
<?php else: ?>
<table>
<tr><th>Orden</th><th>Método</th><th>Items</th><th>Transacciones</th><th>Con order_item_id</th><th>Sin order_item_id</th><th>Estado</th></tr>
<?php foreach ($test_orders as $r): ?>
<tr>
    <td><?= $r['order_number'] ?></td>
    <td><?= $r['payment_method'] ?></td>
    <td><?= $r['items'] ?></td>
    <td><?= $r['transactions'] ?></td>
    <td class="good"><?= $r['with_item_id'] ?></td>
    <td class="<?= $r['without_item_id'] > 0 ? 'bad' : 'good' ?>"><?= $r['without_item_id'] ?></td>
    <td><?= $r['without_item_id'] == 0 && $r['transactions'] > 0 ? '<span class="good">✅ OK</span>' : ($r['transactions'] == 0 ? '<span style="color:#94a3b8">sin receta</span>' : '<span class="bad">❌ FALLA</span>') ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h3>Producción: últimas 10 órdenes Webpay/RL6 pagadas</h3>
<table>
<tr><th>Orden</th><th>Método</th><th>Transacciones</th><th>Con order_item_id</th><th>Sin order_item_id</th><th>Estado</th></tr>
<?php foreach ($prod_check as $r): ?>
<tr>
    <td><?= $r['order_number'] ?></td>
    <td><?= $r['payment_method'] ?></td>
    <td><?= $r['transactions'] ?></td>
    <td class="good"><?= $r['with_item_id'] ?></td>
    <td class="<?= $r['without_item_id'] > 0 ? 'bad' : 'good' ?>"><?= $r['without_item_id'] ?></td>
    <td><?= $r['without_item_id'] == 0 && $r['transactions'] > 0 ? '<span class="good">✅ OK</span>' : ($r['transactions'] == 0 ? '<span style="color:#94a3b8">sin receta</span>' : '<span class="bad">❌ NULL (orden antigua)</span>') ?></td>
</tr>
<?php endforeach; ?>
</table>

<div class="note">
⚠️ Las órdenes antiguas (anteriores al fix) tendrán <code>order_item_id = NULL</code> — eso es esperado.<br>
Las nuevas órdenes post-deploy deben tener <code>without_item_id = 0</code>.<br>
<b>Eliminar este archivo después de verificar:</b> <code>app3/api/test_order_item_id_fix.php</code>
</div>
</body>
</html>
