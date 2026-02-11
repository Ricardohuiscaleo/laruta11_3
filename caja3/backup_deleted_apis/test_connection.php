<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php', // 4 niveles
    __DIR__ . '/../../../../../config.php' // 5 niveles
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
    // Si es POST, usar valores del formulario para prueba
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rut = $_POST['test_rut'] ?? '';
        $secret = $_POST['test_secret'] ?? '';
        $env = $_POST['test_env'] ?? 'development';
    } else {
        // Si es GET, usar valores del config
        $rut = $config['tuu_online_rut'];
        $secret = $config['tuu_online_secret'];
        $env = $config['tuu_online_env'];
    }
    
    if (empty($rut) || empty($secret)) {
        throw new Exception('RUT o clave secreta no configurados');
    }
    
    // URL según ambiente
    $base_url = ($env === 'production') 
        ? 'https://core.payment.haulmer.com/api/v1/payment'
        : 'https://frontend-api.payment.haulmer.dev/v1/payment';
    
    $url = $base_url . '/token/' . $rut;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Authorization: Bearer ' . $secret
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Error cURL: ' . $error);
    }
    
    if ($httpCode !== 200) {
        $responseData = json_decode($response, true);
        throw new Exception('HTTP ' . $httpCode . ': ' . ($responseData['message'] ?? 'Error desconocido'));
    }
    
    $data = json_decode($response, true);
    if (!isset($data['token'])) {
        throw new Exception('Respuesta inválida de TUU');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexión exitosa con TUU',
        'environment' => $env,
        'rut' => $rut,
        'token_received' => true,
        'test_mode' => $_SERVER['REQUEST_METHOD'] === 'POST'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>