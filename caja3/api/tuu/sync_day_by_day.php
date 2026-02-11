<?php
// Sincronizar día por día para evitar errores
header('Content-Type: application/json');

function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $totalImported = 0;
    $results = [];
    $url = 'https://integrations.payment.haulmer.com/Report/get-report';
    
    // Días a sincronizar
    $days = ['2025-09-23', '2025-09-24', '2025-09-25', '2025-09-26'];
    
    foreach ($days as $day) {
        $data = [
            'Filters' => [
                'StartDate' => $day,
                'EndDate' => $day
            ],
            'page' => 1,
            'pageSize' => 50
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'X-API-Key: ' . $config['tuu_api_key'],
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $dayImported = 0;
        
        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if ($result && isset($result['content']['reports'])) {
                foreach ($result['content']['reports'] as $t) {
                    $insertSql = "INSERT IGNORE INTO tuu_pos_transactions 
                        (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $insertStmt = $pdo->prepare($insertSql);
                    $inserted = $insertStmt->execute([
                        $t['saleId'],
                        $t['amount'],
                        $t['status'],
                        $t['posSerialNumber'],
                        $t['typeTransaction'],
                        $t['paymentDataTime'],
                        json_encode($t['extraData']['items'] ?? []),
                        json_encode($t['extraData'] ?? [])
                    ]);
                    if ($inserted && $insertStmt->rowCount() > 0) {
                        $dayImported++;
                    }
                }
            }
        }
        
        $results[] = [
            'date' => $day,
            'http_code' => $httpCode,
            'imported' => $dayImported
        ];
        
        $totalImported += $dayImported;
        usleep(200000); // Pausa 0.2s entre días
    }
    
    echo json_encode([
        'success' => true,
        'total_imported' => $totalImported,
        'daily_results' => $results
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>