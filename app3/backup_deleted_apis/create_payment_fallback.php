<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php en múltiples niveles
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
    
    if (!$input) {
        throw new Exception('Datos de pago requeridos');
    }

    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Generar orden única
    $order_number = 'R11-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    $payment_request_id = 'PAY-' . uniqid();

    // Datos del pago
    $amount = floatval($input['amount']);
    $customer_name = $input['customer_name'] ?? 'Cliente';
    $customer_phone = $input['customer_phone'] ?? '';
    $description = $input['description'] ?? 'Pedido Ruta 11';

    // Método simplificado - crear URL de pago directa
    $base_url = ($config['tuu_online_env'] === 'production') 
        ? 'https://core.payment.haulmer.com'
        : 'https://frontend-api.payment.haulmer.dev';
    
    // Crear URL de pago con parámetros
    $payment_params = [
        'amount' => $amount,
        'currency' => 'CLP',
        'reference' => $order_number,
        'description' => $description,
        'customer_name' => $customer_name,
        'customer_phone' => $customer_phone,
        'return_url' => 'https://app.laruta11.cl/payment-success',
        'cancel_url' => 'https://app.laruta11.cl/checkout?cancelled=1',
        'merchant_id' => $config['tuu_online_rut']
    ];
    
    $payment_url = $base_url . '/checkout?' . http_build_query($payment_params);

    // Guardar orden en BD
    $sql = "INSERT INTO tuu_orders (
        order_number, customer_name, customer_phone, table_number,
        product_name, product_price, installments_total, installment_current,
        installment_amount, tuu_payment_request_id, tuu_idempotency_key,
        tuu_device_used, status, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $order_number,
        $customer_name,
        $customer_phone,
        'Delivery',
        $description,
        $amount,
        1,
        1,
        $amount,
        $payment_request_id,
        'IDM-' . uniqid(),
        'web',
        'pending'
    ]);

    echo json_encode([
        'success' => true,
        'payment_url' => $payment_url,
        'order_number' => $order_number,
        'payment_request_id' => $payment_request_id,
        'amount' => $amount,
        'message' => 'Pago creado exitosamente (método fallback)'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>