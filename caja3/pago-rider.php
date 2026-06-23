<?php
$config = null;
foreach ([__DIR__ . '/api/config.php', __DIR__ . '/config.php', __DIR__ . '/public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}

$token = $_GET['token'] ?? '';
$pagos = [];
$error = null;

if ($token && $config) {
    try {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $stmt = $pdo->prepare("
            SELECT rp.*, r.nombre as rider_nombre,
                   GROUP_CONCAT(DISTINCT o.order_number SEPARATOR ', ') as order_numbers,
                   GROUP_CONCAT(DISTINCT o.delivery_address SEPARATOR ' | ') as delivery_addresses
            FROM rider_pagos rp
            JOIN riders r ON rp.rider_id = r.id
            LEFT JOIN tuu_orders o ON rp.order_id = o.id
            WHERE rp.token = ?
            GROUP BY rp.id
            ORDER BY rp.id DESC
        ");
        $stmt->execute([$token]);
        $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($pagos)) {
            $error = 'Pago no encontrado';
        }
    } catch (Exception $e) {
        $error = 'Error al cargar datos';
    }
} else {
    $error = 'Token inválido';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle de Pago - Delivery</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 480px; margin: 0 auto; padding: 16px; }
        .header { text-align: center; margin-bottom: 24px; }
        .header-icon { font-size: 40px; margin-bottom: 8px; }
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
            <div class="header-icon">🛵</div>
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
                        <div class="card-date"><?= date('d/m/Y', strtotime($pago['fecha'])) ?></div>
                    </div>
                    <div style="text-align:right;">
                        <div class="card-amount">$<?= number_format($pago['monto'], 0, ',', '.') ?></div>
                        <span class="card-badge">Pagado</span>
                    </div>
                </div>

                <div class="card-divider">
                    <div class="card-info">Método de pago: <strong><?= $pago['metodo_pago'] === 'transferencia' ? 'Transferencia' : 'Efectivo' ?></strong></div>
                    <?php if ($pago['order_numbers']): ?>
                        <div class="card-info">Órdenes: <strong><?= htmlspecialchars($pago['order_numbers']) ?></strong></div>
                    <?php endif; ?>
                    <?php if ($pago['delivery_addresses']): ?>
                        <div class="card-info">Direcciones: <strong><?= htmlspecialchars($pago['delivery_addresses']) ?></strong></div>
                    <?php endif; ?>
                </div>

                <?php if ($pago['comprobante_url']): ?>
                <div class="card-divider">
                    <div style="font-size:12px;font-weight:700;color:#374151;margin-bottom:8px;">Comprobante</div>
                    <img src="<?= htmlspecialchars($pago['comprobante_url']) ?>" alt="Comprobante de pago" class="card-img" loading="lazy" />
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="footer">La Ruta 11 · Sistema de pagos delivery</div>
        <?php endif; ?>
    </div>
</body>
</html>
