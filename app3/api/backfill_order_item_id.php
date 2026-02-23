<?php
/**
 * BACKFILL: Rellena order_item_id NULL en inventory_transactions
 * para órdenes Webpay y RL6 antiguas.
 * ELIMINAR después de ejecutar.
 */
header('Content-Type: text/html; charset=utf-8');

$config_paths = [__DIR__ . '/../config.php', __DIR__ . '/../../config.php'];
$config = null;
foreach ($config_paths as $p) { if (file_exists($p)) { $config = require_once $p; break; } }

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$action = $_POST['action'] ?? 'preview';
$messages = [];

// ── Contar afectados ──────────────────────────────────────────────────────────
// Transacciones con order_item_id NULL que tienen orden Webpay o RL6
// y tienen un tuu_order_items matcheable por order_reference + ingredient→product
$preview = $pdo->query("
    SELECT COUNT(DISTINCT it.id) as total_transactions,
           COUNT(DISTINCT it.order_reference) as total_orders
    FROM inventory_transactions it
    JOIN tuu_orders o ON o.order_number = it.order_reference
    JOIN tuu_order_items oi ON oi.order_reference = it.order_reference
    JOIN product_recipes pr ON pr.product_id = oi.product_id AND pr.ingredient_id = it.ingredient_id
    WHERE it.order_item_id IS NULL
      AND o.payment_method IN ('webpay', 'rl6_credit')
      AND o.payment_status = 'paid'
      AND it.ingredient_id IS NOT NULL
")->fetch(PDO::FETCH_ASSOC);

// ── EJECUTAR BACKFILL ─────────────────────────────────────────────────────────
if ($action === 'run') {
    $pdo->beginTransaction();
    try {
        // Para cada transacción NULL, encontrar el order_item_id correcto
        // Matchea: order_reference + ingredient_id → product_recipes → tuu_order_items.product_id
        $updated = $pdo->exec("
            UPDATE inventory_transactions it
            JOIN tuu_orders o ON o.order_number = it.order_reference
            JOIN tuu_order_items oi ON oi.order_reference = it.order_reference
            JOIN product_recipes pr ON pr.product_id = oi.product_id AND pr.ingredient_id = it.ingredient_id
            SET it.order_item_id = oi.id
            WHERE it.order_item_id IS NULL
              AND o.payment_method IN ('webpay', 'rl6_credit')
              AND o.payment_status = 'paid'
              AND it.ingredient_id IS NOT NULL
        ");
        $pdo->commit();
        $messages[] = ['ok', "✅ Backfill completado: <b>$updated</b> transacciones actualizadas con order_item_id."];
    } catch (Exception $e) {
        $pdo->rollBack();
        $messages[] = ['err', '❌ Error: ' . $e->getMessage()];
    }
}

// ── Estado post-acción ────────────────────────────────────────────────────────
$status = $pdo->query("
    SELECT o.payment_method,
           COUNT(it.id) as total,
           SUM(CASE WHEN it.order_item_id IS NOT NULL THEN 1 ELSE 0 END) as con_id,
           SUM(CASE WHEN it.order_item_id IS NULL THEN 1 ELSE 0 END) as sin_id
    FROM inventory_transactions it
    JOIN tuu_orders o ON o.order_number = it.order_reference
    WHERE o.payment_method IN ('webpay', 'rl6_credit')
      AND o.payment_status = 'paid'
      AND it.ingredient_id IS NOT NULL
    GROUP BY o.payment_method
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><title>Backfill order_item_id</title>
<style>
body{font-family:monospace;padding:20px;background:#0f172a;color:#e2e8f0}
h2{color:#38bdf8}h3{color:#94a3b8;margin-top:24px}
.ok{background:#14532d;border-left:4px solid #22c55e;padding:8px 12px;margin:6px 0;border-radius:4px}
.err{background:#450a0a;border-left:4px solid #ef4444;padding:8px 12px;margin:6px 0;border-radius:4px}
.info{background:#1e3a5f;border-left:4px solid #38bdf8;padding:8px 12px;margin:6px 0;border-radius:4px}
table{border-collapse:collapse;width:100%;margin-top:8px}
th{background:#1e293b;padding:8px;text-align:left;font-size:12px;color:#94a3b8}
td{padding:7px 8px;border-bottom:1px solid #1e293b;font-size:12px}
.good{color:#22c55e;font-weight:bold}.bad{color:#ef4444;font-weight:bold}
.btn{padding:10px 20px;border:none;border-radius:6px;cursor:pointer;font-size:14px;font-weight:bold;margin-right:8px}
.btn-run{background:#f97316;color:#fff}.btn-del{background:#ef4444;color:#fff}
.note{background:#1e293b;padding:10px;border-radius:6px;font-size:11px;color:#64748b;margin-top:20px}
</style></head><body>
<h2>🔧 Backfill: order_item_id en órdenes antiguas</h2>
<p style="color:#64748b;font-size:12px">Rellena <code>order_item_id = NULL</code> en <code>inventory_transactions</code> para órdenes Webpay y RL6 anteriores al fix.</p>

<div class="info">
    📊 Transacciones reparables: <b><?= $preview['total_transactions'] ?></b> en <b><?= $preview['total_orders'] ?></b> órdenes
    <br><small style="color:#94a3b8">Match por: order_reference + ingredient_id → product_recipes → tuu_order_items</small>
</div>

<?php foreach ($messages as [$type, $msg]): ?>
<div class="<?= $type ?>"><?= $msg ?></div>
<?php endforeach; ?>

<form method="POST" style="margin:16px 0">
    <button class="btn btn-run" name="action" value="run" onclick="return confirm('¿Ejecutar backfill en producción?')">🔧 Ejecutar Backfill</button>
</form>

<h3>Estado actual por método de pago</h3>
<table>
<tr><th>Método</th><th>Total transacciones</th><th>Con order_item_id</th><th>Sin order_item_id</th><th>Estado</th></tr>
<?php foreach ($status as $r): ?>
<tr>
    <td><?= $r['payment_method'] ?></td>
    <td><?= $r['total'] ?></td>
    <td class="good"><?= $r['con_id'] ?></td>
    <td class="<?= $r['sin_id'] > 0 ? 'bad' : 'good' ?>"><?= $r['sin_id'] ?></td>
    <td><?= $r['sin_id'] == 0 ? '<span class="good">✅ Completo</span>' : '<span class="bad">⚠️ Pendiente</span>' ?></td>
</tr>
<?php endforeach; ?>
</table>

<div class="note">
⚠️ El backfill solo actualiza transacciones donde existe un <code>tuu_order_items</code> con el mismo <code>order_reference</code> y <code>product_id</code> que tenga ese ingrediente en su receta.<br>
Transacciones sin match (producto sin receta o item no encontrado) quedarán con NULL — eso es correcto.<br>
<b>Eliminar este archivo después de ejecutar:</b> <code>app3/api/backfill_order_item_id.php</code>
</div>
</body></html>
