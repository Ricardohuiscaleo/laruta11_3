<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php'
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

$input = json_decode(file_get_contents('php://input'), true);
$orderNumber = $input['order_number'] ?? '';
$paymentMethod = $input['payment_method'] ?? '';

$validMethods = ['cash', 'card', 'transfer', 'webpay', 'pedidosya'];

if (!$orderNumber || !in_array($paymentMethod, $validMethods)) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "UPDATE tuu_orders SET payment_method = ? WHERE order_number = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$paymentMethod, $orderNumber]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
