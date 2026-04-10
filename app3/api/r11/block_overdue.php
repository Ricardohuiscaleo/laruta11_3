<?php
/**
 * Cron Job: R11 Block Overdue — Day 2
 * 
 * Blocks R11 beneficiaries who have outstanding credit and haven't paid
 * by the 1st of the current month.
 * 
 * Schedule: 0 9 2 * * (9 AM on the 2nd of each month)
 */
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

require_once __DIR__ . '/../gmail/get_token_db.php';

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        break;
    }
}

if (!$config) {
    die(json_encode(['success' => false, 'error' => 'Configuración no encontrada']));
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Day 1 of current month — the payment deadline
    $first_of_month = date('Y-m-01');

    // Query R11 beneficiaries with outstanding credit AND no payment this month
    $stmt = $pdo->prepare("
        SELECT id, nombre, email, telefono, relacion_r11, credito_r11_usado, limite_credito_r11
        FROM usuarios
        WHERE es_credito_r11 = 1
          AND credito_r11_aprobado = 1
          AND credito_r11_bloqueado = 0
          AND credito_r11_usado > 0
          AND (fecha_ultimo_pago_r11 IS NULL OR fecha_ultimo_pago_r11 < ?)
        ORDER BY credito_r11_usado DESC
    ");
    $stmt->execute([$first_of_month]);
    $overdue = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($overdue)) {
        echo json_encode(['success' => true, 'message' => 'No hay morosos R11 para bloquear', 'blocked' => 0]);
        exit;
    }

    // Block all overdue users
    $ids = array_column($overdue, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $block_stmt = $pdo->prepare("
        UPDATE usuarios SET credito_r11_bloqueado = 1
        WHERE id IN ($placeholders)
    ");
    $block_stmt->execute($ids);

    // Send block notification emails
    $token_result = getValidGmailToken();
    $access_token = null;
    if (!isset($token_result['error'])) {
        $access_token = $token_result['access_token'];
    } else {
        error_log('R11 block_overdue: Gmail token error - ' . $token_result['error']);
    }

    $admin_email = 'saboresdelaruta11@gmail.com';
    $from = $config['gmail_sender_email'] ?? $admin_email;
    $sent = 0;
    $failed = 0;

    if ($access_token) {
        foreach ($overdue as $user) {
            $html = generateBlockEmail($user);
            $monto_fmt = number_format($user['credito_r11_usado'], 0, ',', '.');
            $subject = "🚫 Crédito R11 Bloqueado - Saldo pendiente $$monto_fmt";

            $ok = sendGmailMessage($access_token, $from, $user['email'], $subject, $html);
            if ($ok) {
                $sent++;
                try {
                    $log = $pdo->prepare("
                        INSERT INTO email_logs (user_id, email_to, email_type, subject, amount, status, sent_at)
                        VALUES (?, ?, 'r11_block', ?, ?, 'sent', NOW())
                    ");
                    $log->execute([$user['id'], $user['email'], $subject, $user['credito_r11_usado']]);
                } catch (Exception $e) {
                    error_log("R11 block email log error: " . $e->getMessage());
                }
            } else {
                $failed++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Bloqueo R11 ejecutado',
        'blocked' => count($overdue),
        'emails_sent' => $sent,
        'emails_failed' => $failed
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


// ─── Helper: Send email via Gmail API ───
function sendGmailMessage($access_token, $from, $to, $subject, $html) {
    $message = "From: La Ruta 11 <$from>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($html));

    $encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encoded]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("R11 block email error ($to): HTTP $http_code - $response");
        return false;
    }
    return true;
}

// ─── Email Template ───
function generateBlockEmail($user) {
    $nombre = htmlspecialchars($user['nombre']);
    $monto = number_format($user['credito_r11_usado'], 0, ',', '.');
    $year = date('Y');

    return "
<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background-color:#fffbeb;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:#fffbeb;padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(239,68,68,0.2);border:1px solid #fecaca;'>

    <tr><td style='background:linear-gradient(135deg,#ef4444 0%,#dc2626 100%);padding:48px 20px;text-align:center;'>
        <div style='width:80px;height:80px;margin:0 auto 16px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;box-shadow:0 10px 20px rgba(0,0,0,0.2);'>🚫</div>
        <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;'>Crédito Bloqueado</h1>
        <p style='color:#fecaca;margin:4px 0 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>Crédito R11</p>
    </td></tr>

    <tr><td style='padding:32px 20px 20px 20px;background:#ffffff;'>
        <div style='text-align:center;margin-bottom:32px;'>
            <h2 style='color:#111827;margin:0 0 12px 0;font-size:24px;font-weight:800;'>$nombre</h2>
            <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>
                Tu crédito R11 ha sido <strong style='color:#ef4444;'>bloqueado</strong> por falta de pago.
            </p>
        </div>

        <div style='background:#fef2f2;border:2px solid #fecaca;border-radius:24px;padding:24px;margin-bottom:24px;text-align:center;'>
            <h3 style='color:#dc2626;margin:0 0 8px 0;font-size:16px;font-weight:800;'>💰 Saldo Pendiente</h3>
            <p style='font-size:36px;font-weight:900;color:#dc2626;margin:0;'>$$monto</p>
        </div>

        <div style='background:#fffbeb;border:2px solid #fde68a;border-radius:16px;padding:16px;margin-bottom:24px;'>
            <p style='color:#92400e;margin:0;font-size:14px;line-height:1.6;'>
                <strong>¿Cómo desbloquear?</strong><br>
                Paga tu saldo pendiente desde la app en <strong>Perfil → Crédito R11 → Pagar</strong> o contacta al administrador para un pago manual.
                Una vez recibido el pago, tu crédito se desbloqueará automáticamente.
            </p>
        </div>
    </td></tr>

    <tr><td style='background-color:#111827;padding:40px 20px;text-align:center;'>
        <p style='color:#6b7280;margin:0;font-size:11px;line-height:1.8;font-weight:500;'>
            Yumbel 2629, Arica, Chile<br>
            <span style='color:#4b5563;'>© $year La Ruta 11 SpA. Sabores con historia.</span>
        </p>
    </td></tr>

</table>
</td></tr></table>
</body></html>";
}
?>
