<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
    $paymentId = $_GET['id'] ?? null;
    $idempotencyKey = $_GET['key'] ?? null;
    
    if (!$paymentId && !$idempotencyKey) {
        throw new Exception('ID de pago o clave de idempotencia requerida');
    }
    
    // Conectar a base de datos
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar en base de datos local primero
    if ($paymentId) {
        $stmt = $pdo->prepare("SELECT * FROM tuu_remote_payments WHERE payment_id = ?");
        $stmt->execute([$paymentId]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM tuu_remote_payments WHERE idempotency_key = ?");
        $stmt->execute([$idempotencyKey]);
    }
    
    $localPayment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$localPayment) {
        throw new Exception('Pago no encontrado');
    }
    
    // Si el estado local no es final, consultar TUU
    if (!in_array($localPayment['status'], ['completed', 'failed', 'canceled'])) {
        
        // Consultar estado en TUU
        if ($paymentId) {
            $url = "https://integrations.payment.haulmer.com/PaymentRequest/{$paymentId}";
        } else {
            $url = "https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentReques/{$idempotencyKey}";
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $config['tuu_api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode === 200) {
            $tuuResult = json_decode($response, true);
            
            if ($tuuResult && isset($tuuResult['status'])) {
                // Mapear estados de TUU a nuestros estados
                $statusMap = [
                    0 => 'pending',    // Pending
                    1 => 'sent',       // Sent
                    2 => 'canceled',   // Canceled
                    3 => 'processing', // Processing
                    4 => 'failed',     // Failed
                    5 => 'completed'   // Completed
                ];
                
                $newStatus = $statusMap[$tuuResult['status']] ?? 'unknown';
                
                // Actualizar estado en base de datos
                $updateStmt = $pdo->prepare("
                    UPDATE tuu_remote_payments 
                    SET status = ?, updated_at = NOW(), tuu_response = ? 
                    WHERE payment_id = ?
                ");
                $updateStmt->execute([
                    $newStatus,
                    json_encode($tuuResult),
                    $localPayment['payment_id']
                ]);
                
                $localPayment['status'] = $newStatus;
                $localPayment['tuu_response'] = json_encode($tuuResult);
            }
        }
    }
    
    // Preparar respuesta
    $response = [
        'success' => true,
        'payment_id' => $localPayment['payment_id'],
        'idempotency_key' => $localPayment['idempotency_key'],
        'status' => $localPayment['status'],
        'amount' => $localPayment['amount'],
        'device' => $localPayment['device'],
        'created_at' => $localPayment['created_at'],
        'updated_at' => $localPayment['updated_at'] ?? $localPayment['created_at']
    ];
    
    // Agregar detalles adicionales si están disponibles
    if ($localPayment['tuu_response']) {
        $tuuData = json_decode($localPayment['tuu_response'], true);
        if ($tuuData) {
            $response['tuu_details'] = $tuuData;
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>