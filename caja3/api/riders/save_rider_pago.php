<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$config = null;
foreach ([__DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../public/config.php'] as $p) {
    if (file_exists($p)) { $config = require $p; break; }
}
if (!$config) { echo json_encode(['success' => false, 'error' => 'Config no encontrado']); exit; }

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Datos requeridos');

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $id = isset($input['id']) ? intval($input['id']) : 0;
    $riderId = intval($input['rider_id'] ?? 0);
    $orderId = isset($input['order_id']) ? intval($input['order_id']) : null;
    $monto = floatval($input['monto'] ?? 0);
    $fecha = $input['fecha'] ?? date('Y-m-d');
    $estado = $input['estado'] ?? 'pendiente';
    $comprobanteUrl = $input['comprobante_url'] ?? null;
    $notas = $input['notas'] ?? null;

    if (!$riderId || !$monto) throw new Exception('rider_id y monto requeridos');

    if ($id) {
        $sql = "UPDATE rider_pagos SET rider_id=?, order_id=?, monto=?, fecha=?, estado=?, comprobante_url=?, notas=?";
        $params = [$riderId, $orderId, $monto, $fecha, $estado, $comprobanteUrl, $notas];
        if ($estado === 'pagado') {
            $sql .= ", pagado_en = NOW()";
        }
        $sql .= " WHERE id = ?";
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    } else {
        $stmt = $pdo->prepare("INSERT INTO rider_pagos (rider_id, order_id, monto, fecha, estado, comprobante_url, notas, pagado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $pagadoEn = $estado === 'pagado' ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$riderId, $orderId, $monto, $fecha, $estado, $comprobanteUrl, $notas, $pagadoEn]);
        $id = $pdo->lastInsertId();
    }

    echo json_encode(['success' => true, 'pago_id' => $id]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
