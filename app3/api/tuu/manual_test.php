<?php
echo "🧪 Testing manual sync...\n";

// Ejecutar el sync manualmente
include __DIR__ . '/simple_sync.php';

echo "✅ Sync ejecutado\n";

// Verificar resultados
$config = null;
for ($i = 1; $i <= 5; $i++) {
    $configPath = str_repeat('../', $i) . 'config.php';
    if (file_exists(__DIR__ . '/' . $configPath)) {
        $config = require_once __DIR__ . '/' . $configPath;
        break;
    }
}

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'],
    $config['app_db_pass']
);

$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tuu_pos_transactions WHERE DATE(payment_date_time) = ?");
$stmt->execute([$today]);
$result = $stmt->fetch();

echo "📊 Transacciones hoy: " . $result['count'] . "\n";

// Verificar log
$logFile = __DIR__ . '/cron_log.txt';
if (file_exists($logFile)) {
    echo "📝 Últimas líneas del log:\n";
    echo file_get_contents($logFile);
}
?>