<?php
// Script súper simple para cron - solo 1 llamada API
file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Iniciando sync...\n", FILE_APPEND);

$config = null;
for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    if (file_exists(__DIR__ . '/' . $configPath)) {
        $config = require_once __DIR__ . '/' . $configPath;
        file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Config cargado desde nivel $i\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - DB Host: " . ($config['app_db_host'] ?? 'VACIO') . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - DB User: " . ($config['app_db_user'] ?? 'VACIO') . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - DB Name: " . ($config['app_db_name'] ?? 'VACIO') . "\n", FILE_APPEND);
        break;
    }
}

if (!$config) {
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - ERROR: config.php no encontrado\n", FILE_APPEND);
    exit;
}

try {
    // Usar las credenciales correctas directamente
    $pdo = new PDO(
        "mysql:host=localhost;dbname=u958525313_app;charset=utf8mb4",
        'u958525313_app',
        'wEzho0-hujzoz-cevzin'
    );
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Conexión DB exitosa\n", FILE_APPEND);
    
    // Solo obtener transacciones de hoy
    $today = date('Y-m-d');
    
    $ch = curl_init('https://integrations.payment.haulmer.com/Report/get-report');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-Key: keWNyXzoj37YvSMi33RPrbppIdTAzqBmpxEJ6yEPnT9UjRfxQQ9CzlcJYPn45aNEw1sXc63Vv32t93Et4KYhQFCbaM3RpA2BOHzwq383mvHDp5YY314x4N7N0XSrz3',
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
    
    if ($response) {
        $result = json_decode($response, true);
        if ($result && isset($result['content']['reports'])) {
            foreach ($result['content']['reports'] as $t) {
                $pdo->prepare("
                    INSERT IGNORE INTO tuu_pos_transactions 
                    (sale_id, amount, status, pos_serial_number, transaction_type, payment_date_time, items_json, extra_data_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([
                    $t['saleId'],
                    $t['amount'],
                    $t['status'],
                    $t['posSerialNumber'],
                    $t['typeTransaction'],
                    $t['paymentDataTime'],
                    json_encode($t['extraData']['items'] ?? []),
                    json_encode($t['extraData'] ?? [])
                ]);
            }
        }
    }
    // Log de éxito con detalles
    $count = isset($result['content']['reports']) ? count($result['content']['reports']) : 0;
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - Sync OK: {$count} transacciones procesadas\n", FILE_APPEND);
    
} catch (Exception $e) {
    // Log de error
    file_put_contents(__DIR__ . '/cron_log.txt', date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>