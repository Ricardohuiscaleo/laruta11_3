<?php
header('Content-Type: application/json');

$logFile = __DIR__ . '/cron_log.txt';

if (!file_exists($logFile)) {
    echo json_encode([
        'status' => 'NO_LOG',
        'message' => 'El cronjob aún no se ha ejecutado',
        'log_file' => $logFile
    ]);
    exit;
}

$logs = file_get_contents($logFile);
$lines = array_filter(array_map('trim', explode("\n", $logs)));
$lastLines = array_slice($lines, -10);

echo json_encode([
    'status' => 'OK',
    'message' => 'Cronjob ejecutándose',
    'last_executions' => $lastLines,
    'total_lines' => count($lines),
    'last_update' => date('Y-m-d H:i:s', filemtime($logFile))
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
