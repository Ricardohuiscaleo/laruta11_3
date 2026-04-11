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

// Enviar email RL6 usando Gmail API de caja3
function sendRL6Email($to, $nombre, $rut, $grado, $unidad, $tipo, $extra = null) {
    require_once __DIR__ . '/gmail/get_token_db.php';
    $token_result = getValidGmailToken();
    if (isset($token_result['error'])) {
        error_log('sendRL6Email: ' . $token_result['error']);
        return false;
    }
    $token = $token_result['access_token'];

    if ($tipo === 'aprobado') {
        $limite  = $extra ?? 50000;
        $meses   = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dias    = max(0, 21 - intval(date('j')));
        $mes     = $meses[date('n') - 1];
        $subject = '🎉 Crédito RL6 Aprobado - La Ruta 11';
        $color   = '#10b981';
        $title   = '🎉 ¡Crédito RL6 Aprobado!';
        $intro   = "Tu solicitud de crédito RL6 ha sido <strong style='color:#10b981;'>APROBADA</strong>.";
        $body    = "
        <div style='background:#d1fae5;padding:20px;border-radius:8px;margin:20px 0;text-align:center;'>
            <p style='font-size:28px;font-weight:bold;color:#10b981;margin:0;'>$" . number_format($limite, 0, ',', '.') . "</p>
            <p style='color:#065f46;margin:4px 0 0;'>Disponible de inmediato</p>
        </div>
        <ol>
            <li>Abre la app y ve a tu Perfil → Crédito</li>
            <li>En el checkout elige \"Pagar con Crédito RL6\"</li>
            <li>Paga el 21 de $mes, te quedan $dias días 😊</li>
        </ol>";
    } elseif ($tipo === 'rechazado') {
        $subject = '❌ Solicitud RL6 No Aprobada - La Ruta 11';
        $color   = '#ef4444';
        $title   = 'Solicitud RL6 No Aprobada';
        $intro   = 'Lamentamos informarte que tu solicitud no pudo ser aprobada en esta ocasión.';
        $body    = "<ul><li>Contáctanos para más información</li><li>Verifica tus datos y vuelve a intentar</li></ul>";
    } else {
        return false;
    }

    $html = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f9fafb;padding:5px;'>
<table width='100%'><tr><td align='center'>
<table width='600' style='background:#fff;border-radius:16px;border:2px solid $color;'>
<tr><td style='background:$color;padding:32px 20px;text-align:center;'>
    <h1 style='color:#fff;margin:0;font-size:24px;'>$title</h1>
</td></tr>
<tr><td style='padding:24px 20px;'>
    <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
    <p>$intro</p>
    $body
    <p style='margin-top:24px;border-top:1px solid #e5e7eb;padding-top:16px;text-align:center;color:#6b7280;font-size:13px;'>
        La Ruta 11 - Sistema RL6<br>
        <a href='https://wa.me/56936227422' style='color:$color;'>WhatsApp: +56 9 3622 7422</a>
    </p>
</td></tr>
</table>
</td></tr></table>
</body></html>";

    $raw = rtrim(strtr(base64_encode(
        "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n" .
        "To: $to\r\n" .
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
        $html
    ), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $raw]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('sendRL6Email webhook: HTTP ' . $httpCode . ' - ' . $response);
        return false;
    }
    return true;
}

// Enviar email R11 usando Gmail API de caja3 (amber/gold theme)
function sendR11Email($to, $nombre, $relacion, $tipo, $extra = null) {
    require_once __DIR__ . '/gmail/get_token_db.php';
    $token_result = getValidGmailToken();
    if (isset($token_result['error'])) {
        error_log('sendR11Email: ' . $token_result['error']);
        return false;
    }
    $token = $token_result['access_token'];

    if ($tipo === 'aprobado') {
        $limite  = $extra ?? 50000;
        $limiteFmt = number_format($limite, 0, ',', '.');
        $subject = '🎉 ¡Bienvenido/a al equipo! - La Ruta 11';
        $color   = '#f59e0b';
        $title   = '🎉 ¡Ya eres parte del equipo!';
        $intro   = "Tu registro ha sido <strong style='color:#f59e0b;'>APROBADO</strong>.";
        $body    = "
        <p style='font-weight:bold;color:#1f2937;margin-bottom:12px;'>Esto es lo que puedes hacer ahora:</p>
        <ol style='line-height:2;'>
            <li>Accede a tu portal en <a href='https://mi.laruta11.cl' style='color:#d97706;font-weight:bold;'>mi.laruta11.cl</a></li>
            <li>Revisa tus turnos y calendario</li>
            <li>Consulta tu liquidación mensual</li>
            <li>Solicita cambios de turno con tus compañeros</li>
            <li>Ya puedes pedir en La Ruta 11 con crédito de hasta <strong>\$$limiteFmt</strong>. El cobro va directo a tu liquidación</li>
        </ol>
        <div style='text-align:center;margin:24px 0;'>
            <a href='https://mi.laruta11.cl' style='display:inline-block;background:#d97706;color:white;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px;'>Ir a mi portal →</a>
        </div>";
    } elseif ($tipo === 'rechazado') {
        $subject = '❌ Solicitud R11 No Aprobada - La Ruta 11';
        $color   = '#ef4444';
        $title   = 'Solicitud R11 No Aprobada';
        $intro   = 'Lamentamos informarte que tu solicitud no pudo ser aprobada en esta ocasión.';
        $body    = "<ul><li>Contáctanos para más información</li><li>Verifica tus datos y vuelve a intentar</li></ul>";
    } else {
        return false;
    }

    $html = "<!DOCTYPE html><html><body style='font-family:sans-serif;background:#f9fafb;padding:5px;'>
<table width='100%'><tr><td align='center'>
<table width='600' style='background:#fff;border-radius:16px;border:2px solid $color;'>
<tr><td style='background:$color;padding:32px 20px;text-align:center;'>
    <h1 style='color:#fff;margin:0;font-size:24px;'>$title</h1>
</td></tr>
<tr><td style='padding:24px 20px;'>
    <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
    <p>$intro</p>
    $body
    <p style='margin-top:24px;border-top:1px solid #e5e7eb;padding-top:16px;text-align:center;color:#6b7280;font-size:13px;'>
        La Ruta 11 - Sistema R11<br>
        <a href='https://wa.me/56936227422' style='color:$color;'>WhatsApp: +56 9 3622 7422</a>
    </p>
</td></tr>
</table>
</td></tr></table>
</body></html>";

    $raw = rtrim(strtr(base64_encode(
        "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n" .
        "To: $to\r\n" .
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
        $html
    ), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token, 'Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $raw]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('sendR11Email webhook: HTTP ' . $httpCode . ' - ' . $response);
        return false;
    }
    return true;
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

                $emailOk = sendRL6Email($usuario['email'], $usuario['nombre'], $usuario['rut'], $usuario['grado_militar'], $usuario['unidad_trabajo'], 'aprobado', $limite);
                $reply = "Credito aprobado para {$usuario['nombre']} (ID: {$user_id})\nLimite: $" . number_format($limite, 0, ',', '.') . "\n" . ($emailOk ? "✅ Email enviado a: {$usuario['email']}" : "⚠️ Email FALLÓ para: {$usuario['email']}");
            } else {
                $pdo->prepare("UPDATE usuarios SET credito_aprobado = 0, limite_credito = 0 WHERE id = ?")
                    ->execute([$user_id]);

                $emailOk = sendRL6Email($usuario['email'], $usuario['nombre'], $usuario['rut'], $usuario['grado_militar'], $usuario['unidad_trabajo'], 'rechazado');
                $reply = "Solicitud rechazada para {$usuario['nombre']} (ID: {$user_id})\n" . ($emailOk ? "✅ Email enviado a: {$usuario['email']}" : "⚠️ Email FALLÓ para: {$usuario['email']}");
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

        // Manejar approve/reject de solicitudes R11
        if (preg_match('/^(approve|reject)_r11_(\d+)(?:_(\d+))?$/', strtolower(trim($text)), $m)) {
            $action  = $m[1];
            $user_id = (int)$m[2];
            $limite  = isset($m[3]) ? (int)$m[3] : 0;

            // Buscar datos del usuario
            $stmt = $pdo->prepare("SELECT nombre, email, rut, relacion_r11 FROM usuarios WHERE id = ? AND es_credito_r11 = 1");
            $stmt->execute([$user_id]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$usuario) {
                sendTelegramMessage($token, $chatId, "Usuario #{$user_id} no encontrado o no es beneficiario R11.");
                break;
            }

            if ($action === 'approve') {
                $pdo->prepare("UPDATE usuarios SET credito_r11_aprobado = 1, limite_credito_r11 = ?, fecha_aprobacion_r11 = NOW() WHERE id = ?")
                    ->execute([$limite, $user_id]);

                // Vincular en tabla personal (crear si no existe)
                $rolMap = [
                    'Planchero/a' => 'planchero',
                    'Cajero/a' => 'cajero',
                    'Rider' => 'rider',
                    'Otro' => 'cajero'
                ];
                $personalRol = $rolMap[$usuario['relacion_r11']] ?? 'cajero';

                $checkPersonal = $pdo->prepare("SELECT id FROM personal WHERE user_id = ?");
                $checkPersonal->execute([$user_id]);
                $existingPersonal = $checkPersonal->fetch(PDO::FETCH_ASSOC);

                if (!$existingPersonal) {
                    $pdo->prepare("INSERT INTO personal (nombre, email, rut, rol, user_id, activo) VALUES (?, ?, ?, ?, ?, 1)")
                        ->execute([$usuario['nombre'], $usuario['email'], $usuario['rut'], $personalRol, $user_id]);
                    $personalMsg = "👤 Vinculado en personal como {$personalRol}";
                } else {
                    $personalMsg = "👤 Ya vinculado en personal (ID: {$existingPersonal['id']})";
                }

                $emailOk = sendR11Email($usuario['email'], $usuario['nombre'], $usuario['relacion_r11'], 'aprobado', $limite);
                $reply = "✅ Aprobado: {$usuario['nombre']} (ID: {$user_id})\nRol: {$usuario['relacion_r11']}\nLímite: $" . number_format($limite, 0, ',', '.') . "\n{$personalMsg}\n" . ($emailOk ? "📧 Email con link a mi.laruta11.cl enviado" : "⚠️ Email FALLÓ");
            } else {
                $pdo->prepare("UPDATE usuarios SET credito_r11_aprobado = 0, limite_credito_r11 = 0 WHERE id = ?")
                    ->execute([$user_id]);

                $emailOk = sendR11Email($usuario['email'], $usuario['nombre'], $usuario['relacion_r11'], 'rechazado');
                $reply = "❌ Solicitud R11 rechazada para {$usuario['nombre']} (ID: {$user_id})\n" . ($emailOk ? "✅ Email enviado a: {$usuario['email']}" : "⚠️ Email FALLÓ para: {$usuario['email']}");
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