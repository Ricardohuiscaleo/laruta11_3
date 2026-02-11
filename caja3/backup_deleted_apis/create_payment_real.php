<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar autoloader del plugin TUU
require_once __DIR__ . '/../../tuu-pluguin/vendor/autoload.php';
use Swipe\lib\Transaction;

// Buscar config.php
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

// Cargar variables de entorno del plugin
$env_file = __DIR__ . '/../../tuu-pluguin/.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $line) {
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value, '"');
        }
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $amount = round($input['amount']);
    $customer_name = $input['customer_name'];
    $customer_phone = $input['customer_phone'];
    $customer_email = $input['customer_email'];
    $order_id = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // URLs según el plugin WooCommerce
    $return_url = 'https://app.laruta11.cl/payment-success?x_result=completed&x_reference=' . $order_id;
    $cancel_url = 'https://app.laruta11.cl/checkout?x_result=failed&x_reference=' . $order_id;
    
    // Obtener token (igual que el plugin)
    $url_base = 'https://core.payment.haulmer.com/api/v1/payment';
    $token_url = $url_base . '/token/' . $config['tuu_online_rut'];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $token_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error obteniendo token: HTTP ' . $httpCode);
    }
    
    $token_data = json_decode($token_response, true);
    if (!isset($token_data['token'])) {
        throw new Exception('Token no recibido');
    }
    
    // Validar token (igual que el plugin)
    $validate_url = $url_base . '/validatetoken';
    $validate_data = json_encode(['token' => $token_data['token']]);
    
    $ch = curl_init($validate_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $validate_data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json',
        'Authorization: Bearer ' . $config['tuu_online_secret']
    ]);
    
    $validate_response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception('Error validando token: HTTP ' . $httpCode);
    }
    
    $secret_keys = json_decode($validate_response, true);
    if (!isset($secret_keys['secret_key']) || !isset($secret_keys['account_id'])) {
        throw new Exception('Claves secretas no recibidas');
    }
    
    // Usar SDK de Swipe (igual que el plugin)
    
    $transaction_data = [
        'platform' => 'ruta11app',
        'paymentMethod' => 'webpay',
        'x_account_id' => $secret_keys['account_id'],
        'x_amount' => $amount,
        'x_currency' => 'CLP',
        'x_customer_email' => $customer_email,
        'x_customer_first_name' => $customer_name,
        'x_customer_last_name' => '',
        'x_customer_phone' => $customer_phone,
        'x_description' => 'Pedido La Ruta 11',
        'x_reference' => $order_id,
        'x_shop_country' => 'CL',
        'x_shop_name' => 'La Ruta 11',
        'x_url_callback' => 'https://app.laruta11.cl/api/tuu/callback.php',
        'x_url_cancel' => $cancel_url,
        'x_url_complete' => $return_url,
        'secret' => $_ENV['SECRET'],
        'dte_type' => 48
    ];
    
    $transaction = new Transaction();
    $transaction->environment = 'PRODUCCION';
    $transaction->setToken($secret_keys['secret_key']);
    $payment_url = $transaction->initTransaction($transaction_data);
    
    if (!filter_var($payment_url, FILTER_VALIDATE_URL)) {
        throw new Exception('URL de pago inválida');
    }
    
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