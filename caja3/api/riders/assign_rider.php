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
    if (!$input || !isset($input['order_id']) || !isset($input['rider_id'])) {
        throw new Exception('order_id y rider_id requeridos');
    }

    $orderId = intval($input['order_id']);
    $riderId = intval($input['rider_id']);

    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'], $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Verify rider exists
    $check = $pdo->prepare("SELECT id, nombre FROM riders WHERE id = ? AND activo = 1");
    $check->execute([$riderId]);
    $rider = $check->fetch(PDO::FETCH_ASSOC);
    if (!$rider) throw new Exception('Rider no encontrado');

    // Verify order exists
    $orderCheck = $pdo->prepare("SELECT id, order_number FROM tuu_orders WHERE id = ?");
    $orderCheck->execute([$orderId]);
    if (!$orderCheck->fetch()) throw new Exception('Orden no encontrada');

    // Assign rider to order
    $pdo->prepare("UPDATE tuu_orders SET rider_id = ? WHERE id = ?")->execute([$riderId, $orderId]);

    // Insert into delivery_assignments if table exists
    try {
        $pdo->prepare("INSERT INTO delivery_assignments (order_id, rider_id, assigned_by, status) VALUES (?, ?, 0, 'assigned')")
            ->execute([$orderId, $riderId]);
    } catch (Exception $e) {
        // Table might not exist, ignore
    }

    echo json_encode(['success' => true, 'rider' => ['id' => $rider['id'], 'nombre' => $rider['nombre']]]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
