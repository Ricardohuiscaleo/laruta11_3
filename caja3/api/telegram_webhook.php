<?php
/**
 * Telegram Webhook Handler
 * Responds to commands like /reporte
 */

$config = require_once __DIR__ . '/../config.php';
$pdo = require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/telegram_helper.php';

$token = $config['telegram_token'] ?? getenv('TELEGRAM_TOKEN');
$authorizedChatId = $config['telegram_chat_id'] ?? getenv('TELEGRAM_CHAT_ID');

// Leer el JSON enviado por Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update || !isset($update['message'])) {
    exit;
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = $message['text'] ?? '';

// 1. Validar Seguridad: Solo responder al dueño (Ricardo)
if ($chatId != $authorizedChatId) {
    sendTelegramMessage($token, $chatId, "🚫 No tienes autorización para usar este bot.");
    exit;
}

// 2. Manejar Comandos
switch (strtolower(trim($text))) {
    case '/start':
        $welcome = "👋 ¡Hola Ricardo! Soy tu bot de inventario de La Ruta 11.\n\n";
        $welcome .= "Escribe /reporte para obtener el estado actual de ventas e ingredientes críticos.";
        sendTelegramMessage($token, $chatId, $welcome);
        break;

    case '/reporte':
    case '/status':
    case 'reporte':
        sendTelegramMessage($token, $chatId, "⏳ Generando reporte diario...");
        try {
            $report = generateInventoryReport($pdo);
            sendTelegramMessage($token, $chatId, $report);
        }
        catch (Exception $e) {
            sendTelegramMessage($token, $chatId, "❌ Error al generar el reporte: " . $e->getMessage());
        }
        break;

    default:
        sendTelegramMessage($token, $chatId, "❓ No entiendo ese comando. Prueba con /reporte.");
        break;
}