<?php
date_default_timezone_set('Etc/GMT+3');
$config = null;
foreach ([__DIR__ . '/api/config.php', __DIR__ . '/config.php', __DIR__ . '/public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$token = $_GET['token'] ?? '';
$error = null;
$riders = [];
$totalGeneral = 0;
$totalPagado = 0;
$totalPendiente = 0;

if ($config) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Get all riders with deliveries for this date
        $sql = "SELECT
                    COALESCE(r.id, 0) as rider_id,
                    COALESCE(r.nombre, 'Sin asignar') as rider_nombre,
                    COUNT(*) as order_count,
                    SUM(o.delivery_fee) + SUM(COALESCE(o.card_surcharge, 0)) as total_fees,
                    COALESCE(SUM(rp.monto), 0) as total_paid,
                    COALESCE(SUM(CASE WHEN rp.id IS NOT NULL THEN 0 ELSE o.delivery_fee + COALESCE(o.card_surcharge, 0) END), 0) as pending_fees,
                    MIN(rp.token) as rider_token
                FROM tuu_orders o
                LEFT JOIN riders r ON o.rider_id = r.id
                LEFT JOIN rider_pagos rp ON rp.order_id = o.id AND rp.estado = 'pagado'
                WHERE o.order_status = 'delivered'
                AND o.delivery_fee > 0
                AND DATE(o.created_at) = ?
                GROUP BY o.rider_id
                ORDER BY total_fees DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fecha]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $riderId = $row['rider_id'];
            // Get individual orders for this rider
            $ordSql = "SELECT o.id, o.order_number, o.delivery_address, o.delivery_fee,
                              o.card_surcharge, o.customer_name, o.customer_phone,
                              rp.monto as paid_amount, rp.estado as pay_status,
                              rp.token, rp.metodo_pago, rp.comprobante_url,
                              o.created_at as order_created_at
                       FROM tuu_orders o
                       LEFT JOIN rider_pagos rp ON rp.order_id = o.id
                       WHERE o.order_status = 'delivered'
                       AND o.delivery_fee > 0
                       AND o.rider_id = ?
                       AND DATE(o.created_at) = ?
                       ORDER BY o.created_at DESC";
            $ordStmt = $pdo->prepare($ordSql);
            $ordStmt->execute([$riderId, $fecha]);
            $orders = $ordStmt->fetchAll(PDO::FETCH_ASSOC);

            $row['orders'] = $orders;
            $riders[] = $row;

            $totalGeneral += $row['total_fees'];
            $totalPagado += $row['total_paid'];
            $totalPendiente += $row['pending_fees'];
        }

        // Get orders without rider assigned
        $unassignedSql = "SELECT o.id, o.order_number, o.delivery_address, o.delivery_fee,
                                 o.card_surcharge, o.customer_name, o.customer_phone
                          FROM tuu_orders o
                          WHERE o.order_status = 'delivered'
                          AND o.delivery_fee > 0
                          AND o.rider_id IS NULL
                          AND DATE(o.created_at) = ?
                          ORDER BY o.created_at DESC";
        $unassignedStmt = $pdo->prepare($unassignedSql);
        $unassignedStmt->execute([$fecha]);
        $unassignedOrders = $unassignedStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($unassignedOrders)) {
            $unassignedFees = 0;
            foreach ($unassignedOrders as $o) {
                $unassignedFees += $o['delivery_fee'] + ($o['card_surcharge'] ?? 0);
            }
            $riders[] = [
                'rider_id' => 0,
                'rider_nombre' => 'Sin asignar',
                'order_count' => count($unassignedOrders),
                'total_fees' => $unassignedFees,
                'total_paid' => 0,
                'pending_fees' => $unassignedFees,
                'orders' => $unassignedOrders,
            ];
            $totalPendiente += $unassignedFees;
        }

    } catch (Exception $e) {
        $error = 'Error al cargar datos: ' . $e->getMessage();
    }
}

$pageTitle = "Rendición Diaria - {$fecha}";
$shareDesc = "Rendición riders {$fecha} · {$totalGeneral} total";
$pageUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
$shareMsg = "📊 *Rendición Diaria* · {$fecha}\n\n💰 Total: \${$totalGeneral}\n✅ Pagado: \${$totalPagado}\n⏳ Pendiente: \${$totalPendiente}\n\n🔗 {$pageUrl}";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta property="og:title" content="Rendición Diaria - La Ruta 11">
    <meta property="og:description" content="<?= htmlspecialchars($shareDesc) ?>">
    <meta property="og:image" content="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/2.jpg">
    <meta property="og:url" content="<?= htmlspecialchars((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}") ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="La Ruta 11">
    <meta name="twitter:card" content="summary_large_image">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f3f4f6; color: #1f2937; }
        .container { max-width: 640px; margin: 0 auto; padding: 16px; }
        .header { text-align: center; margin-bottom: 16px; }
        .header-icon { width: 48px; height: 48px; margin: 0 auto 8px; }
        .header-icon img { width: 100%; height: 100%; object-fit: contain; }
        .header h1 { font-size: 20px; font-weight: 800; }
        .header p { font-size: 13px; color: #6b7280; margin: 2px 0 0; }
        .header .fecha { font-size: 12px; color: #9ca3af; margin-top: 2px; }

        .summary { background: linear-gradient(135deg, #059669, #10b981); color: #fff; border-radius: 14px; padding: 20px; margin-bottom: 16px; display: flex; justify-content: space-between; }
        .summary-item { text-align: center; flex: 1; }
        .summary-item .label { font-size: 10px; text-transform: uppercase; letter-spacing: .5px; opacity: .8; }
        .summary-item .value { font-size: 20px; font-weight: 800; margin-top: 2px; }
        .summary-item .value.green { color: #a7f3d0; }
        .summary-item .value.orange { color: #fde68a; }

        .rider-card { background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-bottom: 12px; overflow: hidden; }
        .rider-header { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; cursor: pointer; user-select: none; transition: background .1s; }
        .rider-header:hover { background: #f9fafb; }
        .rider-info { display: flex; align-items: center; gap: 10px; }
        .rider-avatar { width: 36px; height: 36px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 800; color: #6b7280; text-decoration: none; transition: background .15s; }
        .rider-avatar:hover { background: #d1d5db; }
        .rider-name { font-size: 15px; font-weight: 700; }
        .rider-count { font-size: 11px; color: #6b7280; }
        .rider-stats { text-align: right; }
        .rider-total { font-size: 16px; font-weight: 800; }
        .rider-total.paid { color: #059669; }
        .rider-total.pending { color: #dc2626; }
        .rider-badge { font-size: 9px; padding: 1px 6px; border-radius: 4px; font-weight: 600; display: inline-block; margin-top: 2px; }
        .rider-badge.ok { background: #d1fae5; color: #059669; }
        .rider-badge.pending { background: #fee2e2; color: #dc2626; }

        .rider-orders { border-top: 1px solid #f3f4f6; padding: 8px 16px 12px; }
        .order-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; font-size: 12px; border-bottom: 1px solid #f9fafb; gap: 8px; }
        .order-row:last-child { border-bottom: none; }
        .order-info { flex: 1; min-width: 0; }
        .order-number { font-weight: 700; color: #374151; }
        .order-time { font-size: 9px; color: #9ca3af; }
        .order-address { color: #6b7280; font-size: 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .order-right { text-align: right; flex-shrink: 0; }
        .order-amount { font-weight: 700; white-space: nowrap; }
        .order-paid { color: #059669; }
        .order-pending { color: #dc2626; }
        .order-link { color: #2563eb; text-decoration: none; font-size: 10px; margin-left: 4px; }
        .comprobante-thumb { width: 32px; height: 32px; border-radius: 6px; object-fit: cover; border: 1px solid #e5e7eb; cursor: pointer; margin-top: 2px; display: block; margin-left: auto; }
        .comprobante-thumb:hover { border-color: #2563eb; }

        .empty { text-align: center; padding: 40px 20px; color: #9ca3af; }
        .empty p { font-size: 14px; }
        .error-box { background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px; padding: 24px; text-align: center; }
        .error-box p { color: #dc2626; font-size: 14px; font-weight: 600; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 24px; padding-bottom: 32px; }
        .hidden { display: none; }
        .share-btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 14px; background: #25D366; color: #fff; font-size: 15px; font-weight: 700; border: none; border-radius: 12px; cursor: pointer; margin-top: 16px; transition: background .15s; }
        .share-btn:hover { background: #1ebe5d; }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,.85); z-index: 2000; display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .modal-overlay img { max-width: 92vw; max-height: 90vh; border-radius: 8px; }
    </style>
</head>
<body>
    <div id="lightbox" class="modal-overlay hidden" onclick="this.classList.add('hidden')">
        <img id="lightbox-img" src="" alt="Comprobante" onclick="event.stopPropagation()" />
    </div>
    <div class="container">
        <div class="header">
            <div class="header-icon"><img src="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/2.jpg" alt="La Ruta 11" /></div>
            <h1>Rendición Diaria</h1>
            <p>La Ruta 11 · Pagos delivery</p>
            <div class="fecha"><?= date('d/m/Y', strtotime($fecha)) ?></div>
        </div>

        <?php if ($error): ?>
            <div class="error-box"><p><?= htmlspecialchars($error) ?></p></div>
        <?php elseif (empty($riders)): ?>
            <div class="empty">
                <p>Sin entregas para esta fecha</p>
            </div>
        <?php else: ?>
            <div class="summary">
                <div class="summary-item">
                    <div class="label">Riders</div>
                    <div class="value"><?= count($riders) ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Total</div>
                    <div class="value">$<?= number_format($totalGeneral, 0, ',', '.') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Pagado</div>
                    <div class="value green">$<?= number_format($totalPagado, 0, ',', '.') ?></div>
                </div>
                <div class="summary-item">
                    <div class="label">Pendiente</div>
                    <div class="value orange">$<?= number_format($totalPendiente, 0, ',', '.') ?></div>
                </div>
            </div>

            <?php foreach ($riders as $rider): ?>
            <div class="rider-card">
                <div class="rider-header" onclick="this.nextElementSibling.classList.toggle('hidden')">
                    <div class="rider-info">
                        <a href="<?= $rider['rider_token'] ? '/pago-rider.php?token=' . urlencode($rider['rider_token']) : '#' ?>" class="rider-avatar" target="_blank" onclick="event.stopPropagation()"><?= strtoupper(substr($rider['rider_nombre'], 0, 1)) ?></a>
                        <div>
                            <div class="rider-name"><?= htmlspecialchars($rider['rider_nombre']) ?></div>
                            <div class="rider-count"><?= $rider['order_count'] ?> pedido<?= $rider['order_count'] !== 1 ? 's' : '' ?></div>
                        </div>
                    </div>
                    <div class="rider-stats">
                        <div class="rider-total <?= $rider['pending_fees'] > 0 ? 'pending' : 'paid' ?>">$<?= number_format($rider['total_fees'], 0, ',', '.') ?></div>
                        <?php if ($rider['pending_fees'] > 0): ?>
                            <span class="rider-badge pending">Pendiente</span>
                        <?php else: ?>
                            <span class="rider-badge ok">Pagado</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="rider-orders">
                    <?php foreach ($rider['orders'] as $order):
                        $orderTime = $order['order_created_at'] ? (new DateTime($order['order_created_at'], new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Etc/GMT+3'))->format('d/m/Y H:i') : '';
                    ?>
                    <div class="order-row">
                        <div class="order-info">
                            <div class="order-number"><?= htmlspecialchars($order['order_number']) ?></div>
                            <?php if ($orderTime): ?><div class="order-time"><?= $orderTime ?></div><?php endif; ?>
                            <div class="order-address"><?= htmlspecialchars($order['delivery_address'] ?? 'Sin dirección') ?></div>
                        </div>
                        <div class="order-right">
                            <div class="order-amount <?= !empty($order['pay_status']) && $order['pay_status'] === 'pagado' ? 'order-paid' : 'order-pending' ?>">
                                $<?= number_format($order['delivery_fee'] + ($order['card_surcharge'] ?? 0), 0, ',', '.') ?>
                                <?php if (!empty($order['token'])): ?>
                                    <a href="/pago-rider.php?token=<?= htmlspecialchars($order['token']) ?>" class="order-link" target="_blank">🔗</a>
                                <?php endif; ?>
                            </div>
                            <?php if ($order['metodo_pago'] === 'transferencia' && $order['comprobante_url'] && strpos($order['comprobante_url'], 'http') === 0): ?>
                                <img src="<?= htmlspecialchars($order['comprobante_url']) ?>" alt="comprobante" class="comprobante-thumb" onclick="document.getElementById('lightbox-img').src=this.src;document.getElementById('lightbox').classList.remove('hidden')" onerror="this.style.display='none'" />
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <button class="share-btn" onclick="window.open('https://wa.me/?text='+encodeURIComponent(<?= json_encode($shareMsg) ?>),'_blank')">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                Compartir rendición
            </button>

            <div class="footer">La Ruta 11 · Rendición generada <?= date('d/m/Y H:i') ?></div>
        <?php endif; ?>
    </div>
</body>
</html>
