<?php
// Cron job para renovar token Gmail automáticamente
// Usa BD (gmail_tokens) en vez de archivo local (gmail_token.json)
// El archivo se pierde en cada deploy Docker
$startTime = microtime(true);

require_once __DIR__ . '/../gmail/get_token_db.php';

$result = getValidGmailToken();
$duration = round(microtime(true) - $startTime, 2);

$success = isset($result['access_token']);
$status = $success ? 'success' : 'failed';
$output = $success 
    ? 'Token válido (BD)' 
    : 'Token refresh failed: ' . ($result['error'] ?? 'unknown');

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

echo $output . "\n";
?>
