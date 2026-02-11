<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config
$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
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
        throw new Exception('Datos de entrada inválidos');
    }
    
    // Validar campos requeridos
    $required = ['idempotencyKey', 'amount', 'device', 'description'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }
    
    // Preparar datos para API de pago remoto TUU
    $paymentData = [
        'idempotencyKey' => $input['idempotencyKey'],
        'amount' => intval($input['amount']),
        'device' => $input['device'],
        'description' => $input['description'],
        'dteType' => $input['dteType'] ?? 48,
        'paymentMethod' => $input['paymentMethod'] ?? 1, // 1=crédito, 2=débito
        'extradata' => $input['extradata'] ?? []
    ];
    
    // Agregar campos opcionales
    if (isset($input['cashbackAmount'])) {
        $paymentData['cashbackAmount'] = intval($input['cashbackAmount']);
    }
    if (isset($input['tipAmount'])) {
        $paymentData['tipAmount'] = intval($input['tipAmount']);
    }
    
    // Llamar a API de pago remoto TUU
    $url = 'https://integrations.payment.haulmer.com/RemotePayment/v2/Create';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymentData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Error cURL: $error");
    }
    
    // HTTP 200 = OK, HTTP 201 = Created (ambos exitosos según TUU)
    if ($httpCode !== 200 && $httpCode !== 201) {
        throw new Exception("Error HTTP: $httpCode");
    }
    
    $result = json_decode($response, true);
    
    // Log para debug
    error_log("TUU Response HTTP: $httpCode");
    error_log("TUU Response Body: $response");
    
    if (!$result) {
        throw new Exception('Respuesta inválida de TUU');
    }
    
    // Verificar diferentes formatos de respuesta exitosa de TUU
    $paymentId = null;
    if (isset($result['id'])) {
        $paymentId = $result['id'];
    } elseif (isset($result['paymentRequestId'])) {
        $paymentId = $result['paymentRequestId'];
    } elseif (isset($result['data']['id'])) {
        $paymentId = $result['data']['id'];
    } elseif (isset($result['requestId'])) {
        $paymentId = $result['requestId'];
    } elseif (isset($result['idempotencyKey']) && isset($result['status'])) {
        // Formato v2 de TUU - usar idempotencyKey como ID
        $paymentId = $result['idempotencyKey'];
    }
    
    if ($paymentId) {
        // $paymentId ya está definido arriba
        
        // Intentar guardar en BD (opcional, no crítico)
        try {
            $pdo = new PDO(
                "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
                $config['app_db_user'],
                $config['app_db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            // Crear tabla si no existe
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS tuu_remote_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_id VARCHAR(100) NOT NULL,
                    idempotency_key VARCHAR(36) NOT NULL,
                    amount INT NOT NULL,
                    device VARCHAR(50) NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    request_data JSON,
                    response_data JSON
                )
            ");
            
            $stmt = $pdo->prepare("
                INSERT INTO tuu_remote_payments 
                (payment_id, idempotency_key, amount, device, status, request_data, response_data) 
                VALUES (?, ?, ?, ?, 'pending', ?, ?)
            ");
            
            $stmt->execute([
                $paymentId,
                $input['idempotencyKey'],
                $input['amount'],
                $input['device'],
                json_encode($paymentData),
                json_encode($result)
            ]);
        } catch (Exception $dbError) {
            // Log error pero no fallar
            error_log("Error BD: " . $dbError->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'payment_id' => $paymentId,
            'idempotency_key' => $input['idempotencyKey'],
            'status' => 'pending',
            'message' => 'Solicitud de pago enviada al terminal'
        ]);
        
    } else {
        // Error en la respuesta de TUU - mostrar respuesta completa para debug
        error_log("TUU Error Response: " . json_encode($result));
        
        // Buscar mensaje de error en diferentes campos
        $errorMsg = '';
        if (isset($result['message'])) {
            $errorMsg = $result['message'];
        } elseif (isset($result['error'])) {
            $errorMsg = $result['error'];
        } elseif (isset($result['errors']) && is_array($result['errors'])) {
            $errorMsg = implode(', ', $result['errors']);
        } elseif (isset($result['detail'])) {
            $errorMsg = $result['detail'];
        } else {
            $errorMsg = 'Respuesta inesperada: ' . json_encode($result);
        }
        
        throw new Exception("Error TUU: $errorMsg");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>