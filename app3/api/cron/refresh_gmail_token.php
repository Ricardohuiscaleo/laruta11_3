<?php
// Cron job para renovar token Gmail automáticamente
require_once __DIR__ . '/../auth/gmail/auto_refresh.php';

$result = checkAndRefreshToken();

// Log del resultado
$logMessage = date('Y-m-d H:i:s') . ' - Gmail Token Refresh: ' . ($result ? 'SUCCESS' : 'FAILED') . "\n";
file_put_contents(__DIR__ . '/gmail_refresh.log', $logMessage, FILE_APPEND);

// Respuesta para cron
echo $result ? "Token refreshed successfully\n" : "Token refresh failed\n";
?>