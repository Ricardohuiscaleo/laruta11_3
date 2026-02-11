<?php
header('Content-Type: application/json');

// Verificar última ejecución del cron
$logFile = __DIR__ . '/cron_log.txt';

// Obtener datos de la base
$config = null;
for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    if (file_exists(__DIR__ . '/' . $configPath)) {
        $config = require_once __DIR__ . '/' . $configPath;
        break;
    }
}

if (!$config) {
    throw new Exception('config.php no encontrado');
}

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'],
    $config['app_db_pass']
);

try {
    // Contar transacciones de hoy
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, MAX(created_at) as last_sync FROM tuu_pos_transactions WHERE DATE(payment_date_time) = ?");
    $stmt->execute([$today]);
    $todayData = $stmt->fetch();
    
    // Última transacción general
    $stmt = $pdo->prepare("SELECT MAX(created_at) as last_transaction FROM tuu_pos_transactions");
    $stmt->execute();
    $lastTransaction = $stmt->fetch();
    
    // Verificar si el cron log existe
    $cronStatus = 'No ejecutado';
    $lastCronRun = 'Nunca';
    
    if (file_exists($logFile)) {
        $cronStatus = 'Ejecutado';
        $lastCronRun = date('Y-m-d H:i:s', filemtime($logFile));
    }
    
    echo json_encode([
        'success' => true,
        'cron_status' => $cronStatus,
        'last_cron_run' => $lastCronRun,
        'transactions_today' => $todayData['count'],
        'last_sync' => $todayData['last_sync'],
        'last_transaction_ever' => $lastTransaction['last_transaction'],
        'should_run_every' => '5 minutos',
        'next_expected' => date('Y-m-d H:i:s', strtotime('+5 minutes'))
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>