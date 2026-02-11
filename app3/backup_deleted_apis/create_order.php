<?php
if (file_exists(__DIR__ . '/../config.php')) {
    $config = require_once __DIR__ . '/../config.php';
} else {
    $config_path = __DIR__ . '/../../../config.php';
    if (file_exists($config_path)) {
        $config = require_once $config_path;
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se encontró el archivo de configuración']);
        exit;
    }
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos JSON inválidos']);
    exit;
}

// Validar campos requeridos
$required_fields = ['customer_name', 'product_name', 'product_price', 'installment_amount'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Campo requerido: $field"]);
        exit;
    }
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Generar número de orden único
    $orderNumber = 'TUU' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Insertar pedido en tabla tuu_orders
    $sql = "INSERT INTO tuu_orders (
        order_number,
        customer_name, 
        customer_phone, 
        table_number,
        product_name,
        product_price,
        installments_total,
        installment_current,
        installment_amount,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $orderNumber,
        $input['customer_name'],
        $input['customer_phone'] ?? null,
        $input['table_number'] ?? null,
        $input['product_name'],
        $input['product_price'],
        $input['installments_total'] ?? 1,
        $input['installment_current'] ?? 1,
        $input['installment_amount']
    ]);
    
    $order_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Pedido creado exitosamente'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>