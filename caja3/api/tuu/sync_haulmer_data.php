<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Buscar config.php hasta 5 niveles hacia la raíz
$configPaths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php', 
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$configFound = false;
foreach ($configPaths as $configPath) {
    if (file_exists($configPath)) {
        require_once $configPath;
        $configFound = true;
        break;
    }
}

if (!$configFound) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

try {
    // Obtener datos de TUU API usando credenciales reales
    $apiKey = $config['tuu_api_key'];
    $deviceSerial = $config['tuu_device_serial'];
    $onlineRut = $config['tuu_online_rut'];
    
    // 1. Datos del POS físico
    $posUrl = "https://api.tuu.cl/pos/reports?serial={$deviceSerial}";
    $posHeaders = [
        "Authorization: Bearer {$apiKey}",
        "Content-Type: application/json"
    ];
    
    $posContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $posHeaders)
        ]
    ]);
    
    $posResponse = @file_get_contents($posUrl, false, $posContext);
    $posData = $posResponse ? json_decode($posResponse, true) : [];
    
    // 2. Datos de pagos online (Webpay)
    $onlineUrl = "https://api.tuu.cl/webpay/reports?rut={$onlineRut}";
    $onlineContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $posHeaders)
        ]
    ]);
    
    $onlineResponse = @file_get_contents($onlineUrl, false, $onlineContext);
    $onlineData = $onlineResponse ? json_decode($onlineResponse, true) : [];
    
    // 3. Combinar y procesar datos
    $totalRevenue = 0;
    $totalTransactions = 0;
    $transactions = [];
    
    // Procesar datos POS
    if (isset($posData['transactions'])) {
        foreach ($posData['transactions'] as $transaction) {
            $amount = floatval($transaction['amount'] ?? 0);
            $totalRevenue += $amount;
            $totalTransactions++;
            
            $transactions[] = [
                'id' => $transaction['id'] ?? uniqid(),
                'amount' => $amount,
                'date' => $transaction['date'] ?? date('Y-m-d H:i:s'),
                'type' => 'pos',
                'serial' => $deviceSerial,
                'status' => 'completed'
            ];
        }
    }
    
    // Procesar datos Online
    if (isset($onlineData['transactions'])) {
        foreach ($onlineData['transactions'] as $transaction) {
            $amount = floatval($transaction['amount'] ?? 0);
            $totalRevenue += $amount;
            $totalTransactions++;
            
            $transactions[] = [
                'id' => $transaction['transaction_id'] ?? uniqid(),
                'amount' => $amount,
                'date' => $transaction['created_at'] ?? date('Y-m-d H:i:s'),
                'type' => 'online',
                'customer_name' => $transaction['customer_name'] ?? 'Cliente Online',
                'status' => $transaction['status'] ?? 'completed'
            ];
        }
    }
    
    // 4. Guardar en base de datos local
    $stmt = $pdo->prepare("
        INSERT INTO tuu_sync_cache (
            total_revenue, 
            total_transactions, 
            avg_ticket, 
            sync_date,
            raw_data
        ) VALUES (?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE
        total_revenue = VALUES(total_revenue),
        total_transactions = VALUES(total_transactions),
        avg_ticket = VALUES(avg_ticket),
        sync_date = NOW(),
        raw_data = VALUES(raw_data)
    ");
    
    $avgTicket = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;
    $rawData = json_encode(['pos' => $posData, 'online' => $onlineData]);
    
    $stmt->execute([$totalRevenue, $totalTransactions, $avgTicket, $rawData]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'avg_ticket' => $avgTicket,
            'transactions' => $transactions,
            'sync_time' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>