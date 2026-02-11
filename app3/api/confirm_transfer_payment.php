<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    
    if (!$order_id) {
        throw new Exception('ID de orden requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Verificar que la orden existe y es de transferencia
    $check_sql = "SELECT order_number, payment_method, payment_status FROM tuu_orders WHERE id = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$order_id]);
    $order = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Orden no encontrada');
    }
    
    if ($order['payment_method'] !== 'transfer') {
        throw new Exception('Esta orden no es de transferencia');
    }
    
    if ($order['payment_status'] === 'paid') {
        throw new Exception('Esta orden ya está pagada');
    }
    
    // Actualizar estado de pago a 'paid'
    $update_sql = "UPDATE tuu_orders SET payment_status = 'paid', updated_at = CURRENT_TIMESTAMP WHERE id = ?";
    $update_stmt = $pdo->prepare($update_sql);
    $update_stmt->execute([$order_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago por transferencia confirmado exitosamente',
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    error_log("Confirm Transfer Payment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>