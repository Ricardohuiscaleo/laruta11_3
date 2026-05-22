<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if (session_status() === PHP_SESSION_NONE) { @session_start(); }

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require_once $path; break; }
}
if (!$config) { die(json_encode(['success' => false, 'error' => 'Config no encontrado'])); }

try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $order_id = $input['order_id'] ?? null;
    $action = $input['action'] ?? null;
    $admin_id = $input['admin_id'] ?? null;
    $admin_notes = $input['admin_notes'] ?? null;

    if (!$order_id || !$action || !$admin_id) {
        throw new Exception('Faltan datos requeridos');
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE tuu_orders SET
            receipt_status = 'approved',
            receipt_admin_notes = ?,
            receipt_reviewed_by = ?,
            receipt_reviewed_at = NOW(),
            payment_status = 'paid',
            updated_at = NOW()
            WHERE order_number = ?");
        $stmt->execute([$admin_notes, $admin_id, $order_id]);
        echo json_encode(['success' => true, 'message' => 'Comprobante aprobado. Pedido marcado como pagado.']);
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE tuu_orders SET
            receipt_status = 'rejected',
            receipt_admin_notes = ?,
            receipt_reviewed_by = ?,
            receipt_reviewed_at = NOW(),
            payment_status = 'unpaid',
            updated_at = NOW()
            WHERE order_number = ?");
        $stmt->execute([$admin_notes, $admin_id, $order_id]);
        echo json_encode(['success' => true, 'message' => 'Comprobante rechazado.']);
    } else {
        throw new Exception('Acción inválida. Usa "approve" o "reject"');
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
