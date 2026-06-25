<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$config = null;
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';

    if (!$startDate || !$endDate) {
        throw new Exception('start_date y end_date requeridos');
    }

    $sql = "SELECT 
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.delivery_address,
                o.delivery_fee,
                o.card_surcharge,
                o.delivery_extras,
                o.payment_method,
                o.installment_amount,
                o.rider_id,
                COALESCE(r.nombre, NULL) as rider_nombre,
                o.dispatch_photo_url,
                DATE_FORMAT(DATE_SUB(o.created_at, INTERVAL 3 HOUR), '%H:%i') as hora,
                CASE WHEN rp.id IS NOT NULL THEN 1 ELSE 0 END as is_paid,
                rp.token,
                CASE WHEN rp.comprobante_url LIKE '/uploads/%' THEN NULL ELSE rp.comprobante_url END as comprobante_url,
                rp.metodo_pago
            FROM tuu_orders o
            LEFT JOIN riders r ON o.rider_id = r.id
            LEFT JOIN rider_pagos rp ON rp.order_id = o.id AND rp.estado = 'pagado'
            WHERE COALESCE(o.scheduled_time, o.created_at) >= ?
              AND COALESCE(o.scheduled_time, o.created_at) < ?
              AND o.payment_status = 'paid'
              AND o.order_number NOT LIKE 'RL6-%' AND o.order_number NOT LIKE 'TRF-%'
              AND o.delivery_fee > 0
            ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startDate, $endDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all riders for dropdown
    $riders = $pdo->query("SELECT id, nombre FROM riders WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'riders' => $riders,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
