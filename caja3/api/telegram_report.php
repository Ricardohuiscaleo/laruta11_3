<?php
/**
 * Telegram Reporting Script for Inventory
 * Designed to be run via Cronjob
 */

$config = require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/telegram_helper.php';

$token = $config['telegram_token'] ?? getenv('TELEGRAM_TOKEN');
$chatId = $config['telegram_chat_id'] ?? getenv('TELEGRAM_CHAT_ID');

if (!$token || !$chatId || $token === 'YOUR_BOT_TOKEN_HERE' || $chatId === 'YOUR_CHAT_ID_HERE') {
    error_log("Telegram reporting skipped: Credentials not configured.");
    exit;
}

try {
    $message = generateInventoryReport($pdo);
    $result = sendTelegramMessage($token, $chatId, $message);

    if ($result['success']) {
        echo json_encode(['success' => true, 'message' => 'Reporte enviado a Telegram']);
    }
    else {
        echo json_encode(['success' => false, 'error' => 'Error API Telegram', 'api_response' => $result['response']]);
    }

}
catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}