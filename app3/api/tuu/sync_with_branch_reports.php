<?php
// Usar Branch Reports API (que funciona)
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
    
    $url = 'https://integrations.payment.haulmer.com/BranchReport/branch-report';
    
    $data = [
        'startDate' => '2025-09-23',
        'endDate' => '2025-09-27',
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
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
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
        'success' => true,
        'imported' => $imported,
        'http_code' => $httpCode,
        'api_used' => 'BranchReport',
        'period' => '23-27 septiembre'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>