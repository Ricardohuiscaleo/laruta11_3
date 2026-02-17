<?php
/**
 * Cron job para refrescar el token de Gmail cada 40 minutos
 * Ejecutar: */40 * * * * /usr/bin/php /path/to/caja3/api/gmail/refresh_token_cron.php
 */

require_once __DIR__ . '/get_token.php';

$config = require_once __DIR__ . '/../../config.php';

echo "[" . date('Y-m-d H:i:s') . "] Iniciando refresh de token Gmail...\n";

$token_result = getValidGmailToken();

if (isset($token_result['error'])) {
    echo "[ERROR] " . $token_result['error'] . "\n";
    exit(1);
}

echo "[OK] Token refrescado exitosamente\n";
echo "Access token: " . substr($token_result['access_token'], 0, 20) . "...\n";
exit(0);
?>
