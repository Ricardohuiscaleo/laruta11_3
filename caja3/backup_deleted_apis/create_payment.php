<?php
if (file_exists(__DIR__ . '/../../config.php')) {
    $config = require_once __DIR__ . '/../../config.php';
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
if (!isset($input['amount']) || !isset($input['device_serial'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos requeridos: amount, device_serial']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $amount = intval($input['amount']);
    $deviceSerial = $input['device_serial'];
    $orderId = $input['order_id'] ?? null;
    
    // Generar clave de idempotencia única
    $idempotencyKey = 'RUTA11' . uniqid() . time();
    
    // Crear pago en TUU
    $url = 'https://integrations.payment.haulmer.com/PaymentRequest/Create';
    $data = [
        'idempotencyKey' => $idempotencyKey,
        'amount' => $amount,
        'device' => $deviceSerial,
        'description' => 'Pago La Ruta 11',
        'dteType' => 0
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['tuu_api_key']
        ],
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Error de conexión: ' . $error);
    }
    
    $responseData = json_decode($response, true);
    
    if (($httpCode === 200 || $httpCode === 201) && $responseData && $responseData['code'] === '200') {
        $paymentRequestId = $responseData['content']['paymentRequestId'];
        
        // Guardar en tabla tuu_payments
        $insertSql = "INSERT INTO tuu_payments (
            order_id, order_number, idempotency_key, pos_device, 
            cart_type, device_serial, status, amount, description, 
            tuu_response, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        
        $stmt = $pdo->prepare($insertSql);
        $stmt->execute([
            $orderId,
            $paymentRequestId, // usar como order_number
            $idempotencyKey,
            'POS_RUTA11', // pos_device (nombre del dispositivo)
            'payment', // cart_type
            $deviceSerial, // device_serial (serial real)
            'sent_to_pos',
            $amount,
            'Pago La Ruta 11',
            json_encode($responseData) // guardar respuesta completa de TUU
        ]);
        
        // Si se proporcionó order_id, actualizar también tuu_orders
        if ($orderId) {
            $updateSql = "UPDATE tuu_orders SET 
                tuu_payment_request_id = ?, 
                status = 'sent_to_pos'
                WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$paymentRequestId, $orderId]);
        }
        
        echo json_encode([
            'success' => true,
            'paymentRequestId' => $paymentRequestId,
            'idempotency_key' => $idempotencyKey,
            'amount' => $amount,
            'device_used' => $deviceSerial,
            'message' => 'Pago enviado al POS. Proceda con la tarjeta.'
        ]);
    } else {
        $errorMessage = $responseData['message'] ?? 'Error desconocido';
        $errorCode = $responseData['code'] ?? $httpCode;
        
        echo json_encode([
            'success' => false,
            'error' => $errorMessage,
            'code' => $errorCode,
            'message' => $errorMessage,
            'debug' => [
                'http_code' => $httpCode,
                'response_data' => $responseData,
                'raw_response' => $response
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>