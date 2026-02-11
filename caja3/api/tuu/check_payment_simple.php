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
    $idempotencyKey = $_GET['id'] ?? null;
    
    if (!$idempotencyKey) {
        throw new Exception('ID de pago requerido');
    }
    
    // Consultar directamente a TUU usando idempotencyKey
    $url = "https://integrations.payment.haulmer.com/RemotePayment/v2/GetPaymentReques/{$idempotencyKey}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Error cURL: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Error HTTP: $httpCode");
    }
    
    $result = json_decode($response, true);
    
    if (!$result) {
        throw new Exception('Respuesta inválida de TUU');
    }
    
    // Mapear estados de TUU
    $statusMap = [
        0 => 'pending',    // Pending
        1 => 'sent',       // Sent  
        2 => 'canceled',   // Canceled
        3 => 'processing', // Processing
        4 => 'failed',     // Failed
        5 => 'completed'   // Completed
    ];
    
    $status = 'unknown';
    if (isset($result['status'])) {
        $status = $statusMap[$result['status']] ?? 'unknown';
    }
    
    echo json_encode([
        'success' => true,
        'payment_id' => $idempotencyKey,
        'status' => $status,
        'raw_status' => $result['status'] ?? null,
        'tuu_response' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>