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
    [['text' => '📋 Inventario General', 'callback_data' => '/inventario']],
    [
        ['text' => '🛒 Comprar Ingredientes', 'callback_data' => '/comprar_ing'],
        ['text' => '🥤 Comprar Bebidas', 'callback_data' => '/comprar_beb']
    ]
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
            sendTelegramMessage($token, $chatId, "⏳ Filtrando items para reponer...");
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

            // Si el mensaje es muy largo, Telegram falla. Dividir de forma inteligente por líneas.
            if (strlen($report) > 4000) {
                $lines = explode("\n", $report);
                $currentPart = "";
                foreach ($lines as $line) {
                    if (strlen($currentPart) + strlen($line) + 1 > 4000) {
                        sendTelegramMessage($token, $chatId, $currentPart);
                        $currentPart = $line;
                    }
                    else {
                        $currentPart .= ($currentPart === "" ? "" : "\n") . $line;
                    }
                }
                if ($currentPart !== "") {
                    sendTelegramMessage($token, $chatId, $currentPart);
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

    case '/comprar_ing':
    case 'comprar ingredientes':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Generando lista de compra de ingredientes...");
        try {
            $report = generateShoppingList($pdo, 'ingredientes');
            sendTelegramMessage($token, $chatId, $report, $mainButtons);
        }
        catch (Exception $e) {
            logToTelegram($token, $chatId, "❌ Error Compras Ing: " . $e->getMessage());
        }
        break;

    case '/comprar_beb':
    case 'comprar bebidas':
        if (!$isCallback)
            sendTelegramMessage($token, $chatId, "⏳ Generando lista de compra de bebidas...");
        try {
            $report = generateShoppingList($pdo, 'bebidas');
            sendTelegramMessage($token, $chatId, $report, $mainButtons);
        }
        catch (Exception $e) {
            logToTelegram($token, $chatId, "❌ Error Compras Beb: " . $e->getMessage());
        }
        break;

    default:
        // Manejar approve/reject de solicitudes RL6
        if (preg_match('/^(approve|reject)_rl6_(\d+)(?:_(\d+))?$/', strtolower(trim($text)), $m)) {
            $action  = $m[1];
            $user_id = (int)$m[2];
            $limite  = isset($m[3]) ? (int)$m[3] : 0;

            // Buscar datos del usuario
            $stmt = $pdo->prepare("SELECT nombre, email, rut, grado_militar, unidad_trabajo FROM usuarios WHERE id = ? AND es_militar_rl6 = 1");
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                sendTelegramMessage($token, $chatId, "Usuario #{$user_id} no encontrado o no es militar RL6.");
                break;
            }

            if ($action === 'approve') {
                $pdo->prepare("UPDATE usuarios SET credito_aprobado = 1, limite_credito = ?, fecha_aprobacion_rl6 = NOW() WHERE id = ?")
                    ->execute([$limite, $user_id]);

                // Email de aprobacion
                $send_email_path = __DIR__ . '/../../app3/api/rl6/send_email.php';
                if (file_exists($send_email_path)) {
                    require_once $send_email_path;
                    sendRL6Email($usuario['email'], $usuario['nombre'], $usuario['rut'], $usuario['grado_militar'], $usuario['unidad_trabajo'], 'aprobado', $limite);
                }

                $reply = "Credito aprobado para {$usuario['nombre']} (ID: {$user_id})\nLimite: $" . number_format($limite, 0, ',', '.') . "\nEmail enviado a: {$usuario['email']}";
            } else {
                $pdo->prepare("UPDATE usuarios SET credito_aprobado = 0, limite_credito = 0 WHERE id = ?")
                    ->execute([$user_id]);

                $send_email_path = __DIR__ . '/../../app3/api/rl6/send_email.php';
                if (file_exists($send_email_path)) {
                    require_once $send_email_path;
                    sendRL6Email($usuario['email'], $usuario['nombre'], $usuario['rut'], $usuario['grado_militar'], $usuario['unidad_trabajo'], 'rechazado');
                }

                $reply = "Solicitud rechazada para {$usuario['nombre']} (ID: {$user_id})\nEmail enviado a: {$usuario['email']}";
            }

            // Quitar botones del mensaje original
            if (isset($update['callback_query']['message']['message_id'])) {
                $msg_id = $update['callback_query']['message']['message_id'];
                $ch = curl_init("https://api.telegram.org/bot{$token}/editMessageReplyMarkup");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'chat_id'      => $chatId,
                    'message_id'   => $msg_id,
                    'reply_markup' => json_encode(['inline_keyboard' => []]),
                ]);
                curl_exec($ch);
                curl_close($ch);
            }

            sendTelegramMessage($token, $chatId, $reply, $mainButtons);
            break;
        }

        // Manejar approve/reject de ediciones de producto
        if (preg_match('/^(approve|reject)_edit_(\d+)$/', strtolower(trim($text)), $m)) {
            $action     = $m[1];
            $request_id = (int)$m[2];

            $req = $pdo->prepare("SELECT * FROM product_edit_requests WHERE id = ? AND status = 'pending'");
            $req->execute([$request_id]);
            $edit = $req->fetch(PDO::FETCH_ASSOC);

            if (!$edit) {
                sendTelegramMessage($token, $chatId, "⚠️ Solicitud #{$request_id} no encontrada o ya procesada.");
                break;
            }

            if ($action === 'approve') {
                $pdo->prepare("UPDATE products SET name = ?, description = ? WHERE id = ?")
                    ->execute([$edit['new_name'], $edit['new_description'], $edit['product_id']]);
                $pdo->prepare("UPDATE product_edit_requests SET status = 'approved' WHERE id = ?")
                    ->execute([$request_id]);
                $reply = "✅ *Cambio aprobado* y aplicado en el menú.\n\nProducto ID: {$edit['product_id']}\nNombre: `{$edit['new_name']}`";
            } else {
                $pdo->prepare("UPDATE product_edit_requests SET status = 'rejected' WHERE id = ?")
                    ->execute([$request_id]);
                $reply = "❌ *Cambio rechazado.*\n\nEl producto '{$edit['old_name']}' no fue modificado.";
            }

            // Editar el mensaje original para quitar los botones
            if (!empty($edit['telegram_message_id'])) {
                $url = "https://api.telegram.org/bot{$token}/editMessageReplyMarkup";
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, [
                    'chat_id' => $chatId,
                    'message_id' => $edit['telegram_message_id'],
                    'reply_markup' => json_encode(['inline_keyboard' => []])
                ]);
                curl_exec($ch);
            }

            sendTelegramMessage($token, $chatId, $reply, $mainButtons);
            break;
        }

        if (!$isCallback) {
            sendTelegramMessage($token, $chatId, "❓ No reconozco ese comando. Usa los botones de abajo:", $mainButtons);
        }
        break;
}