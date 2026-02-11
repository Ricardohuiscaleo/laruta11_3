<?php
header('Content-Type: application/json');

// Verificar si save_order_with_items.php existe y es accesible
$save_api_path = __DIR__ . '/save_order_with_items.php';
$save_api_exists = file_exists($save_api_path);

// Verificar logs de PHP
$error_log = ini_get('error_log');
$recent_errors = [];

if ($error_log && file_exists($error_log)) {
    $lines = file($error_log);
    $recent_errors = array_slice($lines, -20); // Últimas 20 líneas
}

// Test de conectividad a la API
$test_url = 'http://' . $_SERVER['HTTP_HOST'] . '/api/tuu/save_order_with_items.php';
$test_data = [
    'order_reference' => 'TEST-' . time(),
    'customer_name' => 'Test User',
    'customer_phone' => '+56912345678',
    'total_amount' => 1000,
    'cart_items' => [
        ['id' => 1, 'name' => 'Test Product', 'price' => 1000, 'quantity' => 1]
    ]
];

$ch = curl_init($test_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$test_response = curl_exec($ch);
$test_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo json_encode([
    'debug_info' => [
        'save_api_exists' => $save_api_exists,
        'save_api_path' => $save_api_path,
        'test_url' => $test_url,
        'test_http_code' => $test_http_code,
        'test_response' => $test_response,
        'curl_error' => $curl_error,
        'recent_errors' => $recent_errors,
        'server_host' => $_SERVER['HTTP_HOST']
    ]
]);
?>