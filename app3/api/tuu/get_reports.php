<?php
// Buscar config.php en múltiples niveles
$config_paths = [
    __DIR__ . '/../../config.php',     // 2 niveles
    __DIR__ . '/../../../config.php',  // 3 niveles  
    __DIR__ . '/../../../../config.php' // 4 niveles
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    http_response_code(500);
    echo json_encode(['error' => 'No se encontró el archivo de configuración']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $serialNumber = $_GET['serial_number'] ?? null;
    $page = intval($_GET['page'] ?? 1);
    $pageSize = min(intval($_GET['page_size'] ?? 10), 20);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Datos JSON inválidos']);
        exit;
    }
    
    $startDate = $input['start_date'] ?? null;
    $endDate = $input['end_date'] ?? null;
    $serialNumber = $input['serial_number'] ?? null;
    $page = intval($input['page'] ?? 1);
    $pageSize = min(intval($input['page_size'] ?? 10), 20);
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

if (!$startDate || !$endDate) {
    http_response_code(400);
    echo json_encode(['error' => 'Campos requeridos: start_date, end_date (formato YYYY-MM-DD)']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato de fecha inválido. Use YYYY-MM-DD']);
    exit;
}

try {
    $requestData = [
        'startDate' => $startDate,
        'endDate' => $endDate,
        'page' => $page,
        'pageSize' => $pageSize
    ];
    
    if ($serialNumber) {
        $requestData['serialNumber'] = $serialNumber;
    }
    
    $url = 'https://integrations.payment.haulmer.com/BranchReport/branch-report';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-API-Key: ' . $config['tuu_api_key']
        ],
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $responseData = json_decode($response, true);
    
    if ($httpCode === 200 && $responseData && $responseData['metadata']['code'] === 'BR-00') {
        echo json_encode([
            'success' => true,
            'data' => $responseData['data'],
            'pagination' => $responseData['pagination'],
            'filters_used' => $requestData
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $responseData['metadata']['message'] ?? 'Error desconocido',
            'code' => $responseData['metadata']['code'] ?? $httpCode
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>