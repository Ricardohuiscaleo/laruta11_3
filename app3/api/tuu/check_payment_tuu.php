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
    __DIR__ . '/../../../config.php'
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
    
    if (!$paymentId) {
        throw new Exception('ID de pago requerido');
    }
    
    // Query directa a TUU API para obtener estado del pago
    $url = "https://integrations.payment.haulmer.com/RemotePayment/v2/Status/{$paymentId}";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
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
    
    // Mapear estados de TUU a nuestros estados
    $status = 'pending';
    if (isset($result['status'])) {
        switch (strtolower($result['status'])) {
            case 'completed':
            case 'approved':
            case 'success':
                $status = 'completed';
                break;
            case 'failed':
            case 'rejected':
            case 'error':
                $status = 'failed';
                break;
            case 'cancelled':
            case 'canceled':
                $status = 'canceled';
                break;
            default:
                $status = 'pending';
        }
    }
    
    echo json_encode([
        'success' => true,
        'payment_id' => $paymentId,
        'status' => $status,
        'tuu_response' => $result
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>