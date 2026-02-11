<?php
// Verificar datos existentes y usar Branch Reports API
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
    
    // Ver datos existentes
    $existingSql = "SELECT DATE(payment_date_time) as date, COUNT(*) as count, MAX(payment_date_time) as last_time 
                   FROM tuu_pos_transactions 
                   WHERE payment_date_time >= '2025-09-20' 
                   GROUP BY DATE(payment_date_time) 
                   ORDER BY date DESC";
    $stmt = $pdo->prepare($existingSql);
    $stmt->execute();
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Intentar Branch Reports API (que funciona)
    $url = 'https://integrations.payment.haulmer.com/BranchReport/branch-report';
    $data = [
        'startDate' => '2025-09-23',
        'endDate' => '2025-09-26',
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
    
    $imported = 0;
    $apiResponse = null;
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $apiResponse = $result;
        
        if ($result && isset($result['data']['transactions'])) {
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
                    $imported++;
                }
            }
        }
    }
    
    echo json_encode([
        'existing_data' => $existing,
        'branch_api_code' => $httpCode,
        'imported_new' => $imported,
        'api_response_sample' => $apiResponse ? array_slice($apiResponse, 0, 2) : null
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>