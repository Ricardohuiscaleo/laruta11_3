<?php
header('Content-Type: application/json');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'Config no encontrado']);
    exit;
}

try {
    // Obtener datos del webhook
    $input = file_get_contents('php://input');
    $webhook_data = json_decode($input, true);
    
    if (!$webhook_data) {
        throw new Exception('Datos de webhook inválidos');
    }
    
    // Log del webhook para debug
    error_log('TUU Webhook recibido: ' . $input);
    
    // Verificar firma del webhook (si TUU la proporciona)
    $signature = $_SERVER['HTTP_X_TUU_SIGNATURE'] ?? '';
    
    // Extraer información del pago
    $order_reference = $webhook_data['x_reference'] ?? null;
    $transaction_id = $webhook_data['x_transaction_id'] ?? null;
    $status = $webhook_data['x_result'] ?? null;
    $amount = $webhook_data['x_amount'] ?? null;
    
    if (!$order_reference) {
        throw new Exception('Referencia de orden requerida');
    }
    
    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Actualizar estado de la orden
    $new_status = match($status) {
        'completed', 'approved' => 'completed',
        'failed', 'rejected' => 'failed',
        'cancelled' => 'cancelled',
        'pending' => 'pending',
        default => 'unknown'
    };
    
    $sql = "UPDATE tuu_orders SET 
            status = ?, 
            payment_status = ?,
            tuu_transaction_id = ?,
            updated_at = NOW()
            WHERE order_number = ?";
    
    $payment_status = ($new_status === 'completed') ? 'paid' : 'unpaid';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$new_status, $payment_status, $transaction_id, $order_reference]);
    
    // Si el pago fue exitoso, enviar notificación
    if ($new_status === 'completed') {
        // Aquí puedes agregar lógica para:
        // - Enviar email de confirmación
        // - Notificar al restaurante
        // - Actualizar inventario
        // - etc.
        
        error_log("Pago completado exitosamente - Orden: $order_reference, Transacción: $transaction_id");
    }
    
    // Responder a TUU que el webhook fue procesado
    echo json_encode([
        'success' => true,
        'message' => 'Webhook procesado correctamente',
        'order_reference' => $order_reference,
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    error_log('Error procesando webhook TUU: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>