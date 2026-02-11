<?php
// Script de sincronización con output para cron
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo "ERROR: config.php no encontrado\n";
    exit(1);
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "Iniciando sincronización TUU - " . date('Y-m-d H:i:s') . "\n";
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $threeDaysAgo = date('Y-m-d', strtotime('-3 days'));
    
    echo "Consultando período: $threeDaysAgo a $yesterday\n";
    
    $url = 'https://integrations.payment.haulmer.com/BranchReport/branch-report';
    
    $requestData = [
        'startDate' => $threeDaysAgo,
        'endDate' => $yesterday,
        'page' => 1,
        'pageSize' => 100
    ];
    
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
        CURLOPT_TIMEOUT => 60
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "API Response Code: $httpCode\n";
    
    $newTransactions = 0;
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && isset($result['data']['transactions'])) {
            echo "Transacciones encontradas: " . count($result['data']['transactions']) . "\n";
            
            foreach ($result['data']['transactions'] as $t) {
                $insertSql = "INSERT IGNORE INTO tuu_pos_transactions 
                    (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $insertStmt = $pdo->prepare($insertSql);
                $inserted = $insertStmt->execute([
                    $t['saleId'],
                    $t['totalAmount'] ?? $t['saleAmount'],
                    $t['status'],
                    $t['serialNumber'],
                    $t['transactionType'],
                    date('Y-m-d H:i:s', strtotime($t['transactionDateTime'])),
                    json_encode($t['items'] ?? []),
                    json_encode($t)
                ]);
                if ($inserted && $insertStmt->rowCount() > 0) {
                    $newTransactions++;
                }
            }
        } else {
            echo "No se encontraron transacciones en la respuesta\n";
        }
    } else {
        echo "ERROR: API falló con código $httpCode\n";
    }
    
    echo "Sincronización completada: $newTransactions nuevas transacciones\n";
    echo "Finalizado: " . date('Y-m-d H:i:s') . "\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>