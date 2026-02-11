<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

// Debug: Log input data
error_log('Input data: ' . print_r($input, true));

// Validar datos requeridos
if (!isset($input['cart_items']) || !isset($input['amount']) || !isset($input['customer_name'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos', 'received' => array_keys($input ?? [])]);
    exit;
}

try {
    // Buscar config.php en múltiples niveles
    $config_paths = [
        __DIR__ . '/../../config.php',     // 2 niveles
        __DIR__ . '/../../../config.php',  // 3 niveles  
        __DIR__ . '/../../../../config.php' // 4 niveles
    ];
    
    $config_loaded = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $config_loaded = true;
            break;
        }
    }
    
    if (!$config_loaded) {
        error_log('Config paths tried: ' . print_r($config_paths, true));
        throw new Exception('Config file not found in any of the expected locations');
    }
    
    // Crear conexión PDO directamente si no existe
    if (!isset($pdo)) {
        try {
            $pdo = new PDO(
                "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
                "u958525313_app",
                "wEzho0-hujzoz-cevzin",
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }
    
    // Definir constantes TUU si no existen (fallback)
    if (!defined('TUU_ACCOUNT_ID')) define('TUU_ACCOUNT_ID', '');
    if (!defined('TUU_SECRET_KEY')) define('TUU_SECRET_KEY', '');
    if (!defined('TUU_TEST_MODE')) define('TUU_TEST_MODE', true);
    if (!defined('TUU_PAYMENT_URL')) define('TUU_PAYMENT_URL', 'https://gateway.tuu.cl/payment');
    if (!defined('TUU_CALLBACK_URL')) define('TUU_CALLBACK_URL', 'https://app.laruta11.cl/api/tuu/callback.php');
    if (!defined('TUU_SUCCESS_URL')) define('TUU_SUCCESS_URL', 'https://app.laruta11.cl/payment-success');
    if (!defined('TUU_CANCEL_URL')) define('TUU_CANCEL_URL', 'https://app.laruta11.cl/checkout');
    
    // Generar order reference único
    $orderRef = 'R11-' . time() . '-' . rand(1000, 9999);
    
    // Crear orden en tuu_orders
    $stmt = $pdo->prepare("
        INSERT INTO tuu_orders (
            order_number, user_id, customer_name, customer_phone, 
            product_name, product_price, installment_amount,
            delivery_type, delivery_address, customer_notes,
            status, payment_status, order_status, delivery_fee
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', 'pending', ?)
    ");
    
    $productNames = array_map(function($item) { return $item['name']; }, $input['cart_items']);
    $productNamesStr = implode(', ', $productNames);
    
    $stmt->execute([
        $orderRef,
        $input['user_id'] ?? null,
        $input['customer_name'],
        $input['customer_phone'] ?? null,
        $productNamesStr,
        $input['amount'],
        $input['amount'],
        $input['delivery_type'] ?? 'pickup',
        $input['delivery_address'] ?? null,
        $input['customer_notes'] ?? null,
        $input['delivery_fee'] ?? 0
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Separar productos principales de extras hardcodeados
    $hardcodedExtras = [
        // Personalizar
        401, 402, 403, 404, 405,
        // Extras
        301, 304, 306, 307, 308, 309
    ];
    
    // Guardar items del carrito
    foreach ($input['cart_items'] as $item) {
        $itemType = 'product';
        
        // Determinar tipo de item
        if (in_array($item['id'], $hardcodedExtras)) {
            if (in_array($item['id'], [401, 402, 403, 404, 405])) {
                $itemType = 'personalizar';
            } else {
                $itemType = 'extras';
            }
        } elseif ($item['id'] >= 200 && $item['id'] < 300) {
            $itemType = 'acompañamiento';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO tuu_order_items (
                order_id, order_reference, product_id, item_type,
                product_name, product_price, quantity, subtotal
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $orderId,
            $orderRef,
            $item['id'],
            $itemType,
            $item['name'],
            $item['price'],
            $item['quantity'],
            $item['price'] * $item['quantity']
        ]);
    }
    
    // Crear pago con TUU
    $tuu_data = [
        'x_account_id' => TUU_ACCOUNT_ID,
        'x_amount' => $input['amount'],
        'x_currency' => 'CLP',
        'x_reference' => $orderRef,
        'x_shop_country' => 'CL',
        'x_test' => TUU_TEST_MODE ? 'true' : 'false',
        'x_url_callback' => TUU_CALLBACK_URL,
        'x_url_complete' => TUU_SUCCESS_URL . '?order=' . $orderRef . '&amount=' . $input['amount'],
        'x_url_cancel' => TUU_CANCEL_URL . '?cancelled=1'
    ];
    
    // Generar signature
    $signature_string = TUU_SECRET_KEY . 
        $tuu_data['x_account_id'] . 
        $tuu_data['x_amount'] . 
        $tuu_data['x_currency'] . 
        $tuu_data['x_reference'] . 
        $tuu_data['x_shop_country'] . 
        $tuu_data['x_test'] . 
        $tuu_data['x_url_callback'] . 
        $tuu_data['x_url_complete'] . 
        $tuu_data['x_url_cancel'];
    
    $tuu_data['x_signature'] = hash('sha256', $signature_string);
    
    // Crear URL de pago
    $payment_url = TUU_PAYMENT_URL . '?' . http_build_query($tuu_data);
    
    echo json_encode([
        'success' => true,
        'payment_url' => $payment_url,
        'order_reference' => $orderRef,
        'order_id' => $orderId
    ]);
    
} catch (Exception $e) {
    error_log('Error in create_payment_with_extras: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'line' => $e->getLine(), 'file' => basename($e->getFile())]);
}
?>