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

    $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
    $fechaFin = $_GET['fecha_fin'] ?? date('Y-m-d');
    $riderId = isset($_GET['rider_id']) ? intval($_GET['rider_id']) : null;

    $sql = "SELECT rp.*, r.nombre as rider_nombre, o.order_number, o.delivery_address, o.delivery_fee
            FROM rider_pagos rp
            LEFT JOIN riders r ON rp.rider_id = r.id
            LEFT JOIN tuu_orders o ON rp.order_id = o.id
            WHERE rp.fecha BETWEEN ? AND ?";
    $params = [$fechaInicio, $fechaFin];

    if ($riderId) {
        $sql .= " AND rp.rider_id = ?";
        $params[] = $riderId;
    }

    $sql .= " ORDER BY rp.fecha DESC, rp.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get pending deliveries (delivered orders without payment record)
    $pendingSql = "SELECT o.id, o.order_number, o.delivery_address, o.delivery_fee,
                          COALESCE(r.nombre, 'Sin asignar') as rider_nombre,
                          o.rider_id, COALESCE(o.scheduled_time, o.created_at) as delivery_time
                   FROM tuu_orders o
                   LEFT JOIN riders r ON o.rider_id = r.id
                   WHERE o.order_status = 'delivered'
                   AND o.delivery_fee > 0
                   AND o.id NOT IN (SELECT order_id FROM rider_pagos WHERE order_id IS NOT NULL)
                   ORDER BY delivery_time DESC";
    $pendingStmt = $pdo->query($pendingSql);
    $pending = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'pagos' => $pagos, 'pending' => $pending]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
