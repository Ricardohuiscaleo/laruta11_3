<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
$tried_paths = [];
foreach ($config_paths as $path) {
    $tried_paths[] = $path;
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode([
        'success' => false, 
        'error' => 'Config no encontrado',
        'tried_paths' => $tried_paths,
        'current_dir' => __DIR__
    ]);
    exit;
}

try {
    $paymentId = $_GET['id'] ?? null;
    
    if (!$paymentId) {
        throw new Exception('ID de pago requerido');
    }
    
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar en tuu_orders primero
    $stmt = $pdo->prepare("SELECT * FROM tuu_orders WHERE tuu_idempotency_key = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$paymentId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo json_encode([
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'pending',
            'message' => 'Orden no encontrada'
        ]);
        exit;
    }
    
    // Buscar pago completado en tuu_reports por monto y fecha aproximada
    $amount = $order['product_price'];
    $orderDate = $order['created_at'];
    
    // Buscar en tuu_reports por monto y rango de fecha (últimos 10 minutos)
    $stmt = $pdo->prepare("
        SELECT * FROM tuu_reports 
        WHERE amount = ? 
        AND status = 'completed'
        AND payment_date_time >= DATE_SUB(?, INTERVAL 10 MINUTE)
        ORDER BY payment_date_time DESC 
        LIMIT 1
    ");
    $stmt->execute([$amount, $orderDate]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($report) {
        // Pago encontrado y completado
        echo json_encode([
            'success' => true,
            'payment_id' => $paymentId,
            'status' => 'completed',
            'transaction_id' => $report['sequence_number'],
            'amount' => $report['amount'],
            'timestamp' => $report['payment_date_time'],
            'message' => 'Pago completado exitosamente',
            'order_id' => $order['id']
        ]);
    } else {
        // Verificar si el pago falló o está pendiente
        if ($order['payment_status'] === 'failed') {
            echo json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'failed',
                'message' => 'Pago falló'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'payment_id' => $paymentId,
                'status' => 'pending',
                'message' => 'Esperando confirmación del pago'
            ]);
        }
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>