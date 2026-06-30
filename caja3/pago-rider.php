<?php
date_default_timezone_set('Etc/GMT+3');
$config = null;
foreach ([__DIR__ . '/api/config.php', __DIR__ . '/config.php', __DIR__ . '/public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}

$orderId = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$token = $_GET['token'] ?? '';
$pagos = [];
$error = null;
$shareTitle = 'Pago Delivery - La Ruta 11';
$shareDesc = 'Detalle de pago delivery';
$shareAmount = '';
$shareRider = '';

if ($orderId && $config) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare("
            SELECT rp.*, r.nombre as rider_nombre,
                   o.order_number, o.delivery_address, o.delivery_fee, o.card_surcharge,
                   o.created_at as order_created_at
            FROM rider_pagos rp
            JOIN riders r ON rp.rider_id = r.id
            LEFT JOIN tuu_orders o ON rp.order_id = o.id
            WHERE rp.order_id = ?
            ORDER BY rp.id DESC
        ");
        $stmt->execute([$orderId]);
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($pagos as &$p) {
            if (strpos($p['comprobante_url'] ?? '', '/uploads/') === 0) $p['comprobante_url'] = null;
        }
        unset($p);
        if (empty($pagos)) { $error = 'Pago no encontrado'; }
    } catch (Exception $e) { $error = 'Error al cargar datos'; }
} elseif ($token && $config) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("
            SELECT rp.id, rp.order_id, rp.monto, rp.comprobante_url, rp.metodo_pago, rp.fecha, rp.token,
                   r.nombre as rider_nombre, r.id as rider_id,
                   o.order_number, o.delivery_address, o.delivery_fee, o.card_surcharge,
                   o.customer_name, o.customer_phone,
                   o.created_at as order_created_at
            FROM rider_pagos rp
            JOIN riders r ON rp.rider_id = r.id
            LEFT JOIN tuu_orders o ON rp.order_id = o.id
            WHERE rp.token = ?
            ORDER BY o.created_at ASC
        ");
        $stmt->execute([$token]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$p) {
            if (strpos($p['comprobante_url'] ?? '', '/uploads/') === 0) $p['comprobante_url'] = null;
        }
        unset($p);

        if (empty($rows)) {
            $error = 'Pago no encontrado';
        } else {
            $pagos[] = [
                'rider_nombre' => $rows[0]['rider_nombre'],
                'rider_id' => $rows[0]['rider_id'],
                'metodo_pago' => $rows[0]['metodo_pago'],
                'comprobante_url' => $rows[0]['comprobante_url'],
                'fecha' => $rows[0]['fecha'],
                'monto' => array_sum(array_column($rows, 'monto')),
                'orders' => array_map(fn($r) => [
                    'order_number' => $r['order_number'],
                    'delivery_address' => $r['delivery_address'],
                    'delivery_fee' => $r['delivery_fee'],
                    'card_surcharge' => $r['card_surcharge'],
                    'customer_name' => $r['customer_name'],
                    'order_created_at' => $r['order_created_at'],
                    'amount' => $r['monto'],
                ], $rows),
            ];
        }
    } catch (Exception $e) {
        $error = 'Error al cargar datos';
    }
} else {
    $error = 'Token inválido';
}

if (!empty($pagos)) {
    $first = $pagos[0];
    $shareRider = htmlspecialchars($first['rider_nombre']);
    $shareAmount = '$' . number_format($first['monto'], 0, ',', '.');
    $orderNumbers = implode(', ', array_filter(array_column($first['orders'], 'order_number')));
    $orderDates = array_filter(array_column($first['orders'], 'order_created_at'));
    $orderDate = !empty($orderDates)
        ? (new DateTime(min($orderDates), new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('d/m/Y')
        : date('d/m/Y', strtotime($first['fecha']));
    $shareMethod = $first['metodo_pago'] === 'transferencia' ? 'Transferencia' : 'Efectivo';
    $orderCount = count($first['orders']);
    $shareTitle = "🛵 Pago Delivery · {$shareRider} · {$shareAmount} · {$orderCount} " . ($orderCount === 1 ? 'entrega' : 'entregas');
    $shareDesc = "{$shareRider} · {$shareAmount} · {$orderCount} " . ($orderCount === 1 ? 'entrega' : 'entregas') . " · {$orderDate} · {$shareMethod}";
    $shareMetaDesc = "{$shareRider} | {$shareAmount} | {$orderCount} " . ($orderCount === 1 ? 'entrega' : 'entregas') . " | {$orderNumbers} | {$orderDate} | {$shareMethod}";
}

$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $shareTitle ?></title>
    <meta property="og:title" content="<?= htmlspecialchars($shareTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($shareMetaDesc) ?>">
    <meta property="og:image" content="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/2.jpg">
    <meta property="og:url" content="<?= htmlspecialchars($pageUrl) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="La Ruta 11">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($shareTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($shareMetaDesc) ?>">
    <meta name="twitter:image" content="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/2.jpg">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header-icon { width: 48px; height: 48px; margin: 0 auto 8px; }
        .header-icon img { width: 100%; height: 100%; object-fit: contain; }
        .header h1 { font-size: 20px; font-weight: 800; color: #1f2937; margin: 0; }
        .header p { font-size: 13px; color: #6b7280; margin: 4px 0 0; }
        .card { background: white; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 20px; margin-bottom: 12px; }
        .card-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .card-name { font-size: 18px; font-weight: 800; color: #1f2937; }
        .card-date { font-size: 12px; color: #6b7280; }
        .card-amount { font-size: 22px; font-weight: 800; color: #059669; }
        .card-badge { font-size: 10px; color: #10b981; background: #d1fae5; padding: 1px 6px; border-radius: 4px; font-weight: 600; display: inline-block; }
        .card-divider { border-top: 1px solid #f3f4f6; padding-top: 12px; margin-top: 12px; }
        .card-info { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
        .card-info strong { color: #374151; }
        .card-img { width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; margin-top: 8px; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 24px; text-align: center; }
        .error-box p { color: #dc2626; font-size: 14px; font-weight: 600; margin: 0; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-icon"><img src="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/2.jpg" alt="La Ruta 11" /></div>
            <h1>Pago Delivery</h1>
            <p>La Ruta 11</p>
        </div>

        <?php if ($error): ?>
            <div class="error-box">
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <?php foreach ($pagos as $pago): ?>
            <div class="card">
                <div class="card-row">
                    <div>
                        <div class="card-name"><?= htmlspecialchars($pago['rider_nombre']) ?></div>
                        <div class="card-date"><?= $pago['fecha'] ? date('d/m/Y', strtotime($pago['fecha'])) : '' ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="card-amount">$<?= number_format($pago['monto'], 0, ',', '.') ?></div>
                        <span class="card-badge">Pagado</span>
                    </div>
                </div>

                <div class="card-divider">
                    <div class="card-info">Método de pago: <strong><?= $pago['metodo_pago'] === 'transferencia' ? 'Transferencia' : 'Efectivo' ?></strong></div>
                </div>

                <?php if (!empty($pago['orders'])): ?>
                <div class="card-divider">
                    <div class="card-info" style="font-weight:700;color:#374151;margin-bottom:8px;">
                        Entregas (<?= count($pago['orders']) ?>)
                    </div>
                    <?php foreach ($pago['orders'] as $order): ?>
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:12px;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <?php if ($order['order_number']): ?>
                                <div style="font-weight:700;color:#374151;"><?= htmlspecialchars($order['order_number']) ?></div>
                            <?php endif; ?>
                            <?php if ($order['order_created_at']): ?>
                                <div style="font-size:10px;color:#9ca3af;"><?= (new DateTime($order['order_created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('d/m/Y H:i') ?> hs</div>
                            <?php endif; ?>
                            <?php if ($order['customer_name']): ?>
                                <div style="font-size:11px;color:#6b7280;"><?= htmlspecialchars($order['customer_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($order['delivery_address']): ?>
                                <div style="font-size:11px;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= htmlspecialchars($order['delivery_address']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div style="font-weight:700;color:#059669;white-space:nowrap;">$<?= number_format($order['amount'] + ($order['card_surcharge'] ?? 0), 0, ',', '.') ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($pago['comprobante_url']): ?>
                <div class="card-divider">
                    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Comprobante</div>
                    <img src="<?= htmlspecialchars($pago['comprobante_url']) ?>" alt="Comprobante de pago" class="card-img" loading="lazy" onerror="this.style.display='none'" />
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="footer">La Ruta 11 · Sistema de pagos delivery</div>
        <?php endif; ?>
    </div>
</body>
</html>
