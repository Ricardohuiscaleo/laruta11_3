<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    $startDate = $input['start_date'] ?? date('Y-m-d', strtotime('-1 day'));
    $endDate = $input['end_date'] ?? date('Y-m-d');
    $serialNumber = $input['serial_number'] ?? '6010B232541609909';
    
    // Llamar a API de Reportes TUU
    $reportData = [
        'Filters' => [
            'StartDate' => $startDate,
            'EndDate' => $endDate,
            'SerialNumber' => $serialNumber
        ],
        'page' => 1,
        'pageSize' => 20
    ];
    
    $url = 'https://integrations.payment.haulmer.com/Report/get-report';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reportData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error HTTP: $httpCode");
    }
    
    $result = json_decode($response, true);
    
    if (!$result || $result['code'] !== '200') {
        throw new Exception('Error en respuesta TUU: ' . ($result['message'] ?? 'Unknown'));
    }
    
    // Conectar a BD
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $insertedCount = 0;
    
    foreach ($result['content']['reports'] as $report) {
        // Verificar si ya existe
        $stmt = $pdo->prepare("SELECT id FROM tuu_reports WHERE sale_id = ? AND sequence_number = ?");
        $stmt->execute([$report['saleId'], $report['sequenceNumber']]);
        
        if ($stmt->fetch()) {
            continue; // Ya existe, saltar
        }
        
        // Insertar nuevo reporte
        $stmt = $pdo->prepare("
            INSERT INTO tuu_reports (
                sale_id, sequence_number, pos_serial_number, status, amount, 
                currency, type_transaction, payment_date_time, extra_data, 
                reconciliations, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $report['saleId'],
            $report['sequenceNumber'],
            $report['posSerialNumber'],
            $report['status'],
            $report['amount'],
            $report['currency'],
            $report['typeTransaction'],
            $report['paymentDataTime'],
            json_encode($report['extraData']),
            json_encode($report['reconciliations'])
        ]);
        
        $insertedCount++;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sincronización completada",
        'total_reports' => count($result['content']['reports']),
        'inserted_count' => $insertedCount,
        'date_range' => "$startDate a $endDate"
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>