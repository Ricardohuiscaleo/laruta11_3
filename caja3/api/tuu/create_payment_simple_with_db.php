<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$config_paths = [
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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $user_id = $input['user_id'] ?? null;
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Guardar en BD si hay user_id
    if ($user_id) {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $order_sql = "INSERT INTO tuu_orders (
            order_number, user_id, customer_name, customer_phone, 
            product_name, product_price, installment_amount, status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $order_stmt = $pdo->prepare($order_sql);
        $order_stmt->execute([
            $order_id, $user_id, $customer_name, $customer_phone,
            'Pedido La Ruta 11', $amount, $amount
        ]);
    }
    
    // Simular éxito y redirigir a página de éxito (pago en local)
    echo json_encode([
        'success' => true,
        'payment_url' => 'https://app.laruta11.cl/payment-success?order=' . $order_id . '&method=local',
        'order_id' => $order_id,
        'user_tracked' => $user_id ? true : false,
        'payment_method' => 'local'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>