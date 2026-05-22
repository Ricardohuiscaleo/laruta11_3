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
    $secret = $input['secret'] ?? '';
    $dry_run = !empty($input['dry_run']);

    if ($secret !== 'migrar-comprobantes-2025') {
        throw new Exception('Secreto inválido');
    }

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->query("SELECT COUNT(*) FROM tuu_orders WHERE receipt_status IS NULL AND (tuu_transaction_id IS NOT NULL OR pagado_con_credito_rl6 = 1 OR pagado_con_credito_r11 = 1)");
    $total = (int)$stmt->fetchColumn();

    if ($dry_run) {
        echo json_encode([
            'success' => true,
            'dry_run' => true,
            'total_pendientes' => $total,
            'message' => "$total pedidos legacy serán marcados como 'approved'"
        ]);
        exit;
    }

    $update = $pdo->prepare("UPDATE tuu_orders SET
        receipt_status = 'approved',
        receipt_path = 'legacy_tuu',
        receipt_original_name = 'comprobante_legacy',
        receipt_admin_notes = 'Migración automática de pagos legacy TUU',
        receipt_reviewed_at = updated_at
        WHERE receipt_status IS NULL
        AND (tuu_transaction_id IS NOT NULL OR pagado_con_credito_rl6 = 1 OR pagado_con_credito_r11 = 1)");
    $update->execute();
    $affected = $update->rowCount();

    echo json_encode([
        'success' => true,
        'migrados' => $affected,
        'message' => "$affected pedidos legacy migrados a comprobantes"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
