<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
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
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $filter = $_GET['filter'] ?? 'pending_review';
    $user_id = $_GET['user_id'] ?? null;

    $sql = "SELECT
        o.order_number,
        o.user_id,
        o.customer_name,
        o.product_name,
        o.amount,
        o.payment_method,
        o.payment_status,
        o.receipt_path,
        o.receipt_status,
        o.receipt_original_name,
        o.receipt_admin_notes,
        o.receipt_reviewed_by,
        o.receipt_reviewed_at,
        o.created_at
        FROM tuu_orders o
        WHERE o.receipt_status IS NOT NULL";

    $params = [];
    if ($filter === 'pending_review') {
        $sql .= " AND o.receipt_status = 'pending_review'";
    } elseif ($filter === 'approved') {
        $sql .= " AND o.receipt_status = 'approved'";
    } elseif ($filter === 'rejected') {
        $sql .= " AND o.receipt_status = 'rejected'";
    }
    if ($user_id) {
        $sql .= " AND o.user_id = ?";
        $params[] = $user_id;
    }
    $sql .= " ORDER BY o.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $receipts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $receipts,
        'total' => count($receipts)
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
