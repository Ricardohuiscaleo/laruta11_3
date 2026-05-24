<?php
header('Content-Type: application/json');

$config_paths = [
    __DIR__ . '/../../caja3/config.php',
    __DIR__ . '/../../../caja3/config.php',
    __DIR__ . '/../../../../caja3/config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}

$tg_token = ($config['telegram_token'] ?? null) ?: getenv('TELEGRAM_TOKEN');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    http_response_code(400);
    die(json_encode(['error' => 'No input']));
}

// ── Callback Query ─────────────────────────────────
if (isset($input['callback_query'])) {
    $cb = $input['callback_query'];
    $cb_id = $cb['id'] ?? '';
    $data  = $cb['data'] ?? '';
    $msg   = $cb['message'] ?? [];
    $chat_id = $msg['chat']['id'] ?? '';
    $msg_id  = $msg['message_id'] ?? '';

    // approve_trf_TRF-xxx  or  reject_trf_TRF-xxx
    if (!preg_match('/^(approve|reject)_trf_(TRF-.+)$/', $data, $m)) {
        answerCallbackQuery($tg_token, $cb_id, 'Acción no reconocida');
        die(json_encode(['ok' => false, 'error' => 'unknown action']));
    }

    $action = $m[1];
    $order_number = $m[2];

    // Load DB config
    $db_host = $config['app_db_host'] ?? getenv('APP_DB_HOST');
    $db_name = $config['app_db_name'] ?? getenv('APP_DB_NAME');
    $db_user = $config['app_db_user'] ?? getenv('APP_DB_USER');
    $db_pass = $config['app_db_pass'] ?? getenv('APP_DB_PASS');

    if (!$db_host || !$db_name || !$db_user || !$db_pass) {
        answerCallbackQuery($tg_token, $cb_id, 'Error de configuración BD');
        die(json_encode(['ok' => false, 'error' => 'DB config missing']));
    }

    try {
        $pdo = new PDO(
            "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
            $db_user, $db_pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare("SELECT * FROM tuu_orders WHERE order_number = ?");
        $stmt->execute([$order_number]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$order) {
            answerCallbackQuery($tg_token, $cb_id, "Orden {$order_number} no encontrada");
            editMessageButtons($tg_token, $chat_id, $msg_id, "❌ Orden no encontrada: {$order_number}");
            die(json_encode(['ok' => false, 'error' => 'order not found']));
        }

        if ($order['receipt_status'] !== 'pending_review') {
            $estado = $order['receipt_status'];
            answerCallbackQuery($tg_token, $cb_id, "Ya fue {$estado}");
            editMessageButtons($tg_token, $chat_id, $msg_id, "⚠️ Ya fue {$estado}: {$order_number}");
            die(json_encode(['ok' => true, 'message' => "already {$estado}"]));
        }

        $user_id = (int) $order['user_id'];
        $monto = (float) $order['installment_amount'];
        $es_rl6 = (bool) $order['pagado_con_credito_rl6'];
        $es_r11 = (bool) $order['pagado_con_credito_r11'];

        if ($action === 'approve') {
            $pdo->beginTransaction();

            // Mark receipt as approved
            $update = $pdo->prepare("UPDATE tuu_orders SET
                receipt_status = 'approved',
                receipt_reviewed_by = 0,
                receipt_reviewed_at = NOW(),
                payment_status = 'paid',
                updated_at = NOW()
                WHERE order_number = ?");
            $update->execute([$order_number]);

            if ($es_rl6) {
                // Insert refund transaction
                $pdo->prepare("INSERT INTO rl6_credit_transactions (user_id, amount, type, description, created_at) VALUES (?, ?, 'refund', 'Pago manual - Transferencia - Aprobado vía Telegram', NOW())")
                    ->execute([$user_id, $monto]);

                // Decrease credito_usado
                $pdo->prepare("UPDATE usuarios SET credito_usado = GREATEST(0, credito_usado - ?), fecha_ultimo_pago = CURDATE(), credito_bloqueado = 0 WHERE id = ?")
                    ->execute([$monto, $user_id]);
            }

            if ($es_r11) {
                // Insert refund transaction
                $pdo->prepare("INSERT INTO r11_credit_transactions (user_id, amount, type, description, order_id) VALUES (?, ?, 'refund', 'Aprobado vía Telegram', ?)")
                    ->execute([$user_id, $monto, $order_number]);

                // Reset credito_r11_usado
                $pdo->prepare("UPDATE usuarios SET credito_r11_usado = 0, fecha_ultimo_pago_r11 = CURDATE(), credito_r11_bloqueado = 0 WHERE id = ?")
                    ->execute([$user_id]);
            }

            $pdo->commit();

            answerCallbackQuery($tg_token, $cb_id, '✅ Comprobante aprobado. Crédito actualizado.');
            editMessageButtons($tg_token, $chat_id, $msg_id, "✅ APROBADO\n━━━━━━━━━━━━━━━━\n📋 {$order_number}\n💰 $" . number_format($monto, 0, ',', '.') . "\n━━━━━━━━━━━━━━━━\nAprobado vía Telegram");

        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE tuu_orders SET
                receipt_status = 'rejected',
                receipt_admin_notes = 'Rechazado vía Telegram',
                receipt_reviewed_by = 0,
                receipt_reviewed_at = NOW(),
                payment_status = 'unpaid',
                updated_at = NOW()
                WHERE order_number = ?")
                ->execute([$order_number]);

            answerCallbackQuery($tg_token, $cb_id, '❌ Comprobante rechazado');
            editMessageButtons($tg_token, $chat_id, $msg_id, "❌ RECHAZADO\n━━━━━━━━━━━━━━━━\n📋 {$order_number}\n━━━━━━━━━━━━━━━━\nRechazado vía Telegram");
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
        answerCallbackQuery($tg_token, $cb_id, 'Error al procesar. Reintenta desde el panel.');
        editMessageButtons($tg_token, $chat_id, $msg_id, "⚠️ Error al procesar: {$order_number}");
        die(json_encode(['ok' => false, 'error' => $e->getMessage()]));
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'webhook alive']);

// ── Helper Functions ────────────────────────────────

function answerCallbackQuery(string $token, string $cbId, string $text): void
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/answerCallbackQuery");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POSTFIELDS => json_encode([
            'callback_query_id' => $cbId,
            'text' => $text,
            'show_alert' => false,
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function editMessageButtons(string $token, string $chatId, int $msgId, string $caption): void
{
    $ch = curl_init("https://api.telegram.org/bot{$token}/editMessageCaption");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POSTFIELDS => json_encode([
            'chat_id' => $chatId,
            'message_id' => $msgId,
            'caption' => $caption,
            'reply_markup' => ['inline_keyboard' => []],
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    curl_exec($ch);
    curl_close($ch);
}
