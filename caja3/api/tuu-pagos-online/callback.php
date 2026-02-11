<?php
// Callback de TUU/Webpay
header('Content-Type: application/json');

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
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
        throw new Exception('config.php not found');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    
    // Actualizar estado del pago
    $response = file_get_contents('http://localhost/api/tuu-pagos-online/update_payment_status.php', false, stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode([
                'order_reference' => $input['order_reference'],
                'status' => $input['status'], // 'completed', 'failed'
                'webpay_response' => $input['webpay_data'] ?? [],
                'tuu_callback_data' => $input
            ])
        ]
    ]));

    // Redirigir usuario
    if ($input['status'] === 'completed') {
        header('Location: https://app.laruta11.cl/success?order=' . $input['order_reference']);
    } else {
        header('Location: https://app.laruta11.cl/failed?order=' . $input['order_reference']);
    }
    
} catch (Exception $e) {
    error_log('TUU Callback Error: ' . $e->getMessage());
    header('Location: https://app.laruta11.cl/error');
}
?>