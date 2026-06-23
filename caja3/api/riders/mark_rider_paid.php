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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $riderId = isset($_POST['rider_id']) ? intval($_POST['rider_id']) : 0;
    $metodoPago = $_POST['metodo_pago'] ?? 'transferencia'; // transferencia | efectivo
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';

    if (!$riderId || !$startDate || !$endDate) {
        throw new Exception('rider_id, start_date y end_date requeridos');
    }

    // Get unpaid delivered orders for this rider in the period
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.delivery_fee, o.delivered_at
        FROM tuu_orders o
        WHERE o.rider_id = ?
          AND COALESCE(o.scheduled_time, o.created_at) >= ?
          AND COALESCE(o.scheduled_time, o.created_at) < ?
          AND o.payment_status = 'paid'
          AND o.delivery_fee > 0
          AND o.id NOT IN (
              SELECT order_id FROM rider_pagos WHERE order_id IS NOT NULL AND estado = 'pagado'
          )
        ORDER BY o.delivered_at ASC
    ");
    $stmt->execute([$riderId, $startDate, $endDate]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo json_encode(['success' => true, 'message' => 'No hay pedidos pendientes para este rider', 'count' => 0]);
        exit;
    }

    // Handle comprobante upload
    $comprobanteUrl = null;
    if (isset($_FILES['comprobante']) && $_FILES['comprobante']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/comprobantes/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $ext = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
        $filename = 'rider_' . $riderId . '_' . time() . '.' . $ext;
        move_uploaded_file($_FILES['comprobante']['tmp_name'], $uploadDir . $filename);
        $comprobanteUrl = '/uploads/comprobantes/' . $filename;
    }

    // Generate a unique token for this payment batch
    $token = bin2hex(random_bytes(16));

    // Insert rider_pagos for each order
    $insertStmt = $pdo->prepare("
        INSERT INTO rider_pagos (rider_id, order_id, monto, fecha, estado, comprobante_url, metodo_pago, token, pagado_en)
        VALUES (?, ?, ?, CURDATE(), 'pagado', ?, ?, ?, NOW())
    ");

    $totalPaid = 0;
    foreach ($orders as $order) {
        $insertStmt->execute([
            $riderId,
            $order['id'],
            $order['delivery_fee'],
            $comprobanteUrl,
            $metodoPago,
            $token,
        ]);
        $totalPaid += floatval($order['delivery_fee']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Pago registrado',
        'count' => count($orders),
        'total' => $totalPaid,
        'token' => $token,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
