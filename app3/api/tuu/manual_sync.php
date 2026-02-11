<?php
header('Content-Type: application/json');

// Buscar config.php hasta 5 niveles
function findConfig($dir, $levels = 5) {
    if ($levels <= 0) return null;
    $configPath = $dir . '/config.php';
    if (file_exists($configPath)) return $configPath;
    return findConfig(dirname($dir), $levels - 1);
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    echo json_encode(['success' => false, 'error' => 'config.php no encontrado']);
    exit;
}

$config = require_once $configPath;

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass']
    );
    
    $today = date('Y-m-d');
    
    $ch = curl_init('https://integrations.payment.haulmer.com/Report/get-report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: ' . $config['tuu_api_key'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'Filters' => [
            'StartDate' => $today,
            'EndDate' => $today
        ],
        'page' => 1,
        'pageSize' => 20
    ]));
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $newTransactions = 0;
    
    if ($response) {
        $result = json_decode($response, true);
        if ($result && isset($result['content']['reports'])) {
            foreach ($result['content']['reports'] as $t) {
                $stmt = $pdo->prepare("
                    INSERT IGNORE INTO tuu_pos_transactions 
                    (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $inserted = $stmt->execute([
                    $t['saleId'],
                    $t['amount'],
                    $t['status'],
                    $t['posSerialNumber'],
                    $t['typeTransaction'],
                    $t['paymentDataTime'],
                    json_encode($t['extraData']['items'] ?? []),
                    json_encode($t['extraData'] ?? [])
                ]);
                if ($inserted && $stmt->rowCount() > 0) {
                    $newTransactions++;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Sincronización completada: $newTransactions nuevas transacciones",
        'new_transactions' => $newTransactions,
        'sync_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>