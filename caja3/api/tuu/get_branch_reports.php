<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
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
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

try {
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // Primer día del mes
    $end_date = $_GET['end_date'] ?? date('Y-m-d'); // Hoy
    
    // Usar la API de Reportes de Haulmer
    $url = 'https://integrations.payment.haulmer.com/Report/get-report';
    
    // Obtener datos de ambos POS
    $devices = [
        $config['tuu_device_serial'], // POS principal
        '6010B232541609909' // POS secundario
    ];
    
    $allTransactions = [];
    
    // Obtener todas las transacciones sin filtrar por dispositivo
    $data = [
        'Filters' => [
            'StartDate' => $start_date,
            'EndDate' => $end_date
        ],
        'page' => 1,
        'pageSize' => 20
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("Error API Branch Report - HTTP $httpCode");
    }
    
    $result = json_decode($response, true);
    
    if (!$result || $result['code'] !== '200') {
        throw new Exception('Error en respuesta de Haulmer: ' . ($result['message'] ?? 'Unknown'));
    }
    
    $allTransactions = $result['content']['reports'] ?? [];
    
    // Guardar transacciones POS en MySQL para combinar con online
    if (!empty($allTransactions)) {
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        foreach ($allTransactions as $t) {
            $stmt = $pdo->prepare("
                INSERT INTO tuu_pos_transactions 
                (sale_id, pos_serial, amount, transaction_type, status, payment_date, items_data, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE 
                amount = VALUES(amount), status = VALUES(status), items_data = VALUES(items_data)
            ");
            
            $items_json = json_encode($t['extraData']['items'] ?? []);
            $stmt->execute([
                $t['saleId'],
                $t['posSerialNumber'],
                $t['amount'],
                $t['typeTransaction'],
                $t['status'],
                $t['paymentDataTime'],
                $items_json
            ]);
        }
    }
    
    // Transformar datos para compatibilidad con todos los detalles POS
    $transactions = [];
    foreach ($allTransactions as $t) {
        // Determinar canal y fuente basado en posSerialNumber
        $isWebTransaction = empty($t['posSerialNumber']) || $t['posSerialNumber'] === 'N/A';
        $channel = $isWebTransaction ? 'WEB' : 'Punto de venta';
        $commission = (!$isWebTransaction && $t['typeTransaction'] === 'DEBIT') ? '0.94%' : 'N/A';
        $paymentSource = $isWebTransaction ? 'online' : 'pos';
        
        $transactions[] = [
            'saleId' => $t['saleId'],
            'date' => $t['paymentDataTime'],
            'totalAmount' => $t['amount'],
            'status' => $t['status'],
            'serialNumber' => $t['posSerialNumber'],
            'sequenceNumber' => $t['sequenceNumber'] ?? 'N/A',
            'locationId' => 'N/A',
            'address' => 'N/A',
            'cardBrand' => 'N/A',
            'cardBin' => 'N/A',
            'cardOrigin' => 'N/A',
            'cardIssuer' => 'N/A',
            'transactionType' => $t['typeTransaction'],
            'documentType' => 'N/A',
            'currencyCode' => $t['currency'] ?? 'CLP',
            'saleAmount' => $t['extraData']['amountWithoutCommission'] ?? $t['amount'],
            'tipAmount' => 0,
            'cashbackAmount' => 0,
            'installmentType' => 'N/A',
            'installmentCount' => 0,
            'acquirerId' => 'N/A',
            'instance' => 'N/A',
            'items' => $t['extraData']['items'] ?? [],
            // Campos adicionales de la página TUU
            'channel' => $channel,
            'commission' => $commission,
            'activityCode' => '561000',
            'cardLastFour' => substr($t['cardBin'] ?? '', -4) ?: 'N/A',
            // Datos para transacciones WEB
            'customer_name' => $isWebTransaction ? 'Cliente Web' : 'Cliente POS',
            'tuu_transaction_id' => $isWebTransaction ? $t['saleId'] : null,
            'payment_source' => $paymentSource
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transactions' => $transactions,
            'total_amount' => array_sum(array_column($transactions, 'totalAmount')),
            'total_transactions' => count($transactions)
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>