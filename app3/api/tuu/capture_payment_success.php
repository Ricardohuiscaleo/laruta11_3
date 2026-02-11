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
    // Obtener datos del usuario actual
    session_start();
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        // Intentar obtener user_id de la sesión de autenticación
        $auth_response = file_get_contents('/api/auth/check_session.php');
        $auth_data = json_decode($auth_response, true);
        if ($auth_data && $auth_data['authenticated']) {
            $user_id = $auth_data['user']['id'];
        }
    }
    
    $order_ref = $_GET['order'] ?? $_POST['order'] ?? null;
    
    if (!$order_ref) {
        throw new Exception('Order reference requerida');
    }
    
    if (!$user_id) {
        // Si no hay usuario logueado, no hacer nada (pago anónimo)
        echo json_encode([
            'success' => true,
            'message' => 'Pago anónimo procesado',
            'user_tracked' => false
        ]);
        exit;
    }
    
    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar la orden en tuu_orders
    $order_sql = "SELECT * FROM tuu_orders WHERE order_number = ?";
    $order_stmt = $pdo->prepare($order_sql);
    $order_stmt->execute([$order_ref]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Orden no encontrada');
    }
    
    // Si la orden no tiene user_id, actualizarla
    if (!$order['user_id']) {
        $update_sql = "UPDATE tuu_orders SET user_id = ? WHERE order_number = ?";
        $update_stmt = $pdo->prepare($update_sql);
        $update_stmt->execute([$user_id, $order_ref]);
    }
    
    // Verificar si ya existe en tuu_pagos_online
    $check_sql = "SELECT id FROM tuu_pagos_online WHERE order_reference = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$order_ref]);
    
    if (!$check_stmt->fetch()) {
        // Insertar en tuu_pagos_online
        $payment_sql = "INSERT INTO tuu_pagos_online (
            user_id, order_reference, amount, payment_method, 
            status, customer_name, customer_email, customer_phone, 
            completed_at
        ) VALUES (?, ?, ?, 'webpay', 'completed', ?, ?, ?, NOW())";
        
        $payment_stmt = $pdo->prepare($payment_sql);
        $payment_stmt->execute([
            $user_id,
            $order_ref,
            $order['product_price'],
            $order['customer_name'],
            '', // email se puede obtener del usuario
            $order['customer_phone']
        ]);
        
        error_log("Payment Success: Pago capturado para usuario ID $user_id - Orden: $order_ref");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Pago capturado exitosamente',
        'user_tracked' => true,
        'order_ref' => $order_ref
    ]);
    
} catch (Exception $e) {
    error_log("Payment Success Capture Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>