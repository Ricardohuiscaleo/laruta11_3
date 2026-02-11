<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuración directa (sin dependencias)
$config = [
    'tuu_online_rut' => '78194739-3',
    'tuu_online_secret' => '4bd3b7629ea289797fda5a988c1e2a6dee8f710b883657f7cbed7ce0ad5a09397e2c7698fda707da'
];

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos de entrada inválidos');
    }
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Obtener token TUU
    $token_url = 'https://core.payment.haulmer.com/api/v1/payment/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error obteniendo token TUU: HTTP ' . $httpCode);
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token TUU no recibido');
    }
    
    // Crear formulario HTML que se auto-envía (método del plugin WooCommerce)
    $form_html = '<!DOCTYPE html>
<html>
<head>
    <title>Redirigiendo a TUU...</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; background: #f5f5f5; }
        .loader { border: 4px solid #f3f3f3; border-top: 4px solid #ff6b35; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 20px auto; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <h2>🔄 Procesando tu pago...</h2>
    <div class="loader"></div>
    <p>Serás redirigido automáticamente a TUU Pago Online</p>
    
    <form id="tuuForm" method="POST" action="https://core.payment.haulmer.com/api/v1/payment/create">
        <input type="hidden" name="token" value="' . htmlspecialchars($token_data['token']) . '">
        <input type="hidden" name="amount" value="' . $amount . '">
        <input type="hidden" name="order_id" value="' . htmlspecialchars($order_id) . '">
        <input type="hidden" name="customer_name" value="' . htmlspecialchars($customer_name) . '">
        <input type="hidden" name="customer_phone" value="' . htmlspecialchars($customer_phone) . '">
        <input type="hidden" name="customer_email" value="' . htmlspecialchars($customer_email) . '">
        <input type="hidden" name="return_url" value="https://app.laruta11.cl/payment-success">
        <input type="hidden" name="cancel_url" value="https://app.laruta11.cl/checkout?cancelled=1">
    </form>
    
    <script>
        setTimeout(function() {
            document.getElementById("tuuForm").submit();
        }, 2000);
    </script>
</body>
</html>';
    
    // Crear archivo temporal
    $temp_file = 'payment_' . $order_id . '.html';
    file_put_contents(__DIR__ . '/' . $temp_file, $form_html);
    
    $payment_url = 'https://app.laruta11.cl/api/tuu/' . $temp_file;
    
    echo json_encode([
        'success' => true,
        'payment_url' => $payment_url
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>