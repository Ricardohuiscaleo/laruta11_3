<?php
// Cron job para renovar token Gmail automáticamente
$startTime = microtime(true);
require_once __DIR__ . '/../auth/gmail/auto_refresh.php';

$result = checkAndRefreshToken();
$duration = round(microtime(true) - $startTime, 2);
$status = $result ? 'success' : 'failed';
$output = $result ? 'Token refreshed successfully' : 'Token refresh failed';

// Log del resultado
$logMessage = date('Y-m-d H:i:s') . ' - Gmail Token Refresh: ' . ($result ? 'SUCCESS' : 'FAILED') . "\n";
@file_put_contents(__DIR__ . '/gmail_refresh.log', $logMessage, FILE_APPEND);

// Registrar en cron_executions (MySQL)
try {
    $configPath = __DIR__ . '/../../config.php';
    if (file_exists($configPath)) {
        $config = include $configPath;
        $db = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'], $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $now = date('Y-m-d H:i:s');
        $stmt = $db->prepare("INSERT INTO cron_executions (command, name, status, output, duration_seconds, started_at, finished_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute(['gmail-token-refresh', 'Gmail Token Refresh', $status, $output, $duration, $now, $now, $now, $now]);
    }
} catch (Exception $e) {
    // No romper el cron si falla el logging
}

// Respuesta para cron
echo $output . "\n";
?>