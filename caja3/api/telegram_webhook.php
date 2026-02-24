<?php
/**
 * Telegram Webhook Handler
 * Responds to commands and buttons
 */

$config = require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/telegram_helper.php';

$token = $config['telegram_token'] ?? getenv('TELEGRAM_TOKEN');
$authorizedChatId = $config['telegram_chat_id'] ?? getenv('TELEGRAM_CHAT_ID');

// Leer el JSON enviado por Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    exit;
}

$chatId = null;
$text = '';
$isCallback = false;

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = $update['message']['text'] ?? '';
}
elseif (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
    $text = $update['callback_query']['data'];
    $isCallback = true;
}

if (!$chatId) {
    exit;
}

// 1. Validar Seguridad: Solo responder al dueño (Ricardo)
if ($chatId != $authorizedChatId) {
    sendTelegramMessage($token, $chatId, "🚫 No tienes autorización para usar este bot.");
    exit;
}

// Definir Botones
$mainButtons = [
    [['text' => '📊 Reporte del Turno', 'callback_data' => '/reporte']],
    [['text' => '📋 Inventario General', 'callback_data' => '/inventario']]
];

// 2. Manejar Comandos
switch (strtolower(trim($text))) {
    case '/start':
        $welcome = "👋 ¡Hola Ricardo! Soy tu bot de gestión de La Ruta 11.\n\n";
        $welcome .= "Selecciona una opción para ver el estado del negocio:";
        sendTelegramMessage($token, $chatId, $welcome, $mainButtons);
        break;

    case '/reporte':
    case 'reporte':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Generando reporte de turno...");
        try {
            $report = generateInventoryReport($pdo);
            sendTelegramMessage($token, $chatId, $report, $mainButtons);
        }
        catch (Exception $e) {
            sendTelegramMessage($token, $chatId, "❌ Error: " . $e->getMessage());
        }
        break;

    case '/inventario':
    case 'inventario':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Consultando inventario general...");
        try {
            $report = generateGeneralInventoryReport($pdo);
            sendTelegramMessage($token, $chatId, $report, $mainButtons);
        }
        catch (Exception $e) {
            sendTelegramMessage($token, $chatId, "❌ Error: " . $e->getMessage());
        }
        break;

    default:
        if (!$isCallback) {
            sendTelegramMessage($token, $chatId, "❓ No reconozco ese comando. Usa los botones de abajo:", $mainButtons);
        }
        break;
}