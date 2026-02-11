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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $user_id = $input['user_id'] ?? null;
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Guardar en BD si hay user_id (SOLO ESTO ES NUEVO)
    if ($user_id) {
        try {
            $pdo = new PDO(
                "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
                $config['app_db_user'],
                $config['app_db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $order_sql = "INSERT INTO tuu_orders (
                order_number, user_id, customer_name, customer_phone, 
                product_name, product_price, installment_amount, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')";
            
            $order_stmt = $pdo->prepare($order_sql);
            $order_stmt->execute([
                $order_id, $user_id, $customer_name, $customer_phone,
                'Pedido La Ruta 11', $amount, $amount
            ]);
        } catch (Exception $db_error) {
            // Si falla BD, continuar con el pago (no bloquear)
            error_log("Error BD: " . $db_error->getMessage());
        }
    }
    
    // RESTO DEL CÓDIGO ORIGINAL QUE FUNCIONABA
    $url_base = 'https://core.payment.haulmer.com/api/v1/payment';
    $token_url = $url_base . '/token/' . $config['tuu_online_rut'];
    
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
        throw new Exception('Error obteniendo token TUU');
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido');
    }
    
    // Crear formulario HTML (MÉTODO ORIGINAL QUE FUNCIONABA)
    $form_html = '<!DOCTYPE html>
<html>
<head>
    <title>Procesando Pago...</title>
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
    <p>Orden: ' . htmlspecialchars($order_id) . '</p>
    <p>Usuario: ' . htmlspecialchars($customer_name) . '</p>
    
    <form id="tuuForm" method="POST" action="https://core.payment.haulmer.com/api/v1/payment">
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
    
    $temp_file = 'payment_' . $order_id . '.html';
    file_put_contents(__DIR__ . '/' . $temp_file, $form_html);
    
    echo json_encode([
        'success' => true,
        'payment_url' => 'https://app.laruta11.cl/api/tuu/' . $temp_file,
        'order_id' => $order_id,
        'user_tracked' => $user_id ? true : false
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>