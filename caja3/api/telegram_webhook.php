<?php
/**
 * Telegram Webhook Handler
 * Responds to commands and buttons with debug logging in chat
 */

$config = require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/telegram_helper.php';

$token = $config['telegram_token'] ?? getenv('TELEGRAM_TOKEN');
$authorizedChatId = $config['telegram_chat_id'] ?? getenv('TELEGRAM_CHAT_ID');

// Función para loguear errores directamente al chat del usuario
function logToTelegram($token, $chatId, $errorMsg)
{
    $debugMsg = "🔍 *LOG DE SISTEMA*:\n" . substr($errorMsg, 0, 3000);
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $debugMsg,
        'parse_mode' => 'Markdown'
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_exec($ch);
    curl_close($ch);
}

// Capturar errores fatales y enviarlos al chat
register_shutdown_function(function () use ($token, $authorizedChatId) {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR || $error['type'] === E_COMPILE_ERROR)) {
        logToTelegram($token, $authorizedChatId, "❌ FATAL ERROR: {$error['message']} en {$error['file']}:{$error['line']}");
    }
});

set_exception_handler(function ($e) use ($token, $authorizedChatId) {
    logToTelegram($token, $authorizedChatId, "❌ UNCAUGHT EXCEPTION: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
});

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
    // Si queremos debuggear intentos externos descomentar:
    // logToTelegram($token, $authorizedChatId, "⚠️ Intento de acceso de ChatID desconocido: " . $chatId);
    exit;
}

// Definir Botones
$mainButtons = [
    [['text' => '📊 Reporte del Turno', 'callback_data' => '/reporte']],
    [['text' => '⚠️ Inventario Crítico', 'callback_data' => '/critico']],
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
            logToTelegram($token, $chatId, "❌ Error Reporte: " . $e->getMessage());
        }
        break;

    case '/critico':
    case 'critico':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Filtrando items críticos...");
        try {
            $report = generateGeneralInventoryReport($pdo, true);
            sendTelegramMessage($token, $chatId, $report, $mainButtons);
        }
        catch (Exception $e) {
            logToTelegram($token, $chatId, "❌ Error Críticos: " . $e->getMessage());
        }
        break;

    case '/inventario':
    case 'inventario':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Consultando inventario general...");
        try {
            $report = generateGeneralInventoryReport($pdo, false);

            // Si el mensaje es muy largo, Telegram falla. Dividir si es necesario.
            if (strlen($report) > 4000) {
                $parts = str_split($report, 4000);
                foreach ($parts as $p) {
                    sendTelegramMessage($token, $chatId, $p);
                }
                sendTelegramMessage($token, $chatId, "✅ Fin de lista.", $mainButtons);
            }
            else {
                sendTelegramMessage($token, $chatId, $report, $mainButtons);
            }
        }
        catch (Exception $e) {
            logToTelegram($token, $chatId, "❌ Error Inventario: " . $e->getMessage());
        }
        break;

    default:
        if (!$isCallback) {
            sendTelegramMessage($token, $chatId, "❓ No reconozco ese comando. Usa los botones de abajo:", $mainButtons);
        }
        break;
}