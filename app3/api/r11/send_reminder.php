<?php
/**
 * Cron Job: R11 Payment Reminder — Day 28
 * 
 * Sends reminder emails to R11 beneficiaries with outstanding credit,
 * plus a summary email to admin with the full debtor list.
 * 
 * Schedule: 0 9 28 * * (9 AM on the 28th of each month)
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

    // Query R11 beneficiaries with outstanding credit
    $stmt = $pdo->query("
        SELECT id, nombre, email, telefono, relacion_r11, credito_r11_usado, limite_credito_r11
        FROM usuarios
        WHERE es_credito_r11 = 1
          AND credito_r11_aprobado = 1
          AND credito_r11_usado > 0
        ORDER BY credito_r11_usado DESC
    ");
    $debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($debtors)) {
        echo json_encode(['success' => true, 'message' => 'No hay deudores R11 este mes', 'count' => 0]);
        exit;
    }

    $token_result = getValidGmailToken();
    if (isset($token_result['error'])) {
        die(json_encode(['success' => false, 'error' => 'Gmail token: ' . $token_result['error']]));
    }
    $access_token = $token_result['access_token'];

    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $mes_actual = $meses[date('n') - 1];
    $sent = 0;
    $failed = 0;
    $total_debt = 0;
    $admin_email = 'saboresdelaruta11@gmail.com';
    $from = $config['gmail_sender_email'] ?? $admin_email;

    // Send individual reminder to each debtor
    foreach ($debtors as $debtor) {
        $total_debt += floatval($debtor['credito_r11_usado']);
        $monto_fmt = number_format($debtor['credito_r11_usado'], 0, ',', '.');

        $html = generateReminderEmail($debtor, $monto_fmt, $mes_actual);
        $subject = "⏰ Recordatorio: Pago Crédito R11 - $$monto_fmt pendiente";

        $ok = sendGmailMessage($access_token, $from, $debtor['email'], $subject, $html);
        if ($ok) {
            $sent++;
            // Log email
            try {
                $log = $pdo->prepare("
                    INSERT INTO email_logs (user_id, email_to, email_type, subject, amount, status, sent_at)
                    VALUES (?, ?, 'r11_reminder', ?, ?, 'sent', NOW())
                ");
                $log->execute([$debtor['id'], $debtor['email'], $subject, $debtor['credito_r11_usado']]);
            } catch (Exception $e) {
                error_log("R11 reminder email log error: " . $e->getMessage());
            }
        } else {
            $failed++;
        }
    }

    // Send admin summary
    $total_fmt = number_format($total_debt, 0, ',', '.');
    $admin_html = generateAdminSummaryEmail($debtors, $total_fmt, $mes_actual);
    $admin_subject = "📋 Resumen Cobro R11 - " . count($debtors) . " deudores - $$total_fmt total";
    sendGmailMessage($access_token, $from, $admin_email, $admin_subject, $admin_html);

    echo json_encode([
        'success' => true,
        'message' => "Recordatorios R11 enviados",
        'sent' => $sent,
        'failed' => $failed,
        'total_debtors' => count($debtors),
        'total_debt' => $total_debt
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
        error_log("R11 reminder email error ($to): HTTP $http_code - $response");
        return false;
    }
    return true;
}


// ─── Email Templates ───

function generateReminderEmail($debtor, $monto_fmt, $mes) {
    $nombre = htmlspecialchars($debtor['nombre']);
    $relacion = htmlspecialchars($debtor['relacion_r11'] ?? 'trabajador');
    $year = date('Y');

    return "
<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background-color:#fffbeb;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:#fffbeb;padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(245,158,11,0.2);border:1px solid #fde68a;'>

    <tr><td style='background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);padding:48px 20px;text-align:center;'>
        <div style='width:80px;height:80px;margin:0 auto 16px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;box-shadow:0 10px 20px rgba(0,0,0,0.2);'>⏰</div>
        <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;'>Recordatorio de Pago</h1>
        <p style='color:#fef3c7;margin:4px 0 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>Crédito R11</p>
    </td></tr>

    <tr><td style='padding:32px 20px 20px 20px;background:#ffffff;'>
        <div style='text-align:center;margin-bottom:32px;'>
            <h2 style='color:#111827;margin:0 0 12px 0;font-size:24px;font-weight:800;'>Hola, $nombre 👋</h2>
            <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>
                Te recordamos que tienes un saldo pendiente en tu crédito R11 que será descontado el <strong>día 1 de " . htmlspecialchars($mes) . "</strong>.
            </p>
        </div>

        <div style='background:#fffbeb;border:2px solid #fde68a;border-radius:24px;padding:24px;margin-bottom:24px;text-align:center;'>
            <h3 style='color:#d97706;margin:0 0 8px 0;font-size:16px;font-weight:800;'>💰 Monto a Descontar</h3>
            <p style='font-size:36px;font-weight:900;color:#d97706;margin:0;'>$$monto_fmt</p>
            <p style='color:#92400e;margin:8px 0 0;font-size:13px;'>Se descontará de tu sueldo el día 1</p>
        </div>

        <div style='background:#f0fdf4;border:2px solid #bbf7d0;border-radius:16px;padding:16px;margin-bottom:24px;text-align:center;'>
            <p style='color:#059669;margin:0;font-size:14px;font-weight:600;'>
                💡 Si deseas pagar antes, puedes hacerlo desde la app en <strong>Perfil → Crédito R11 → Pagar</strong>
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

function generateAdminSummaryEmail($debtors, $total_fmt, $mes) {
    $count = count($debtors);
    $year = date('Y');
    $fecha = date('d/m/Y');

    $rows = '';
    foreach ($debtors as $i => $d) {
        $bg = $i % 2 === 0 ? '#ffffff' : '#fffbeb';
        $nombre = htmlspecialchars($d['nombre']);
        $relacion = htmlspecialchars($d['relacion_r11'] ?? '-');
        $monto = number_format($d['credito_r11_usado'], 0, ',', '.');
        $rows .= "<tr style='background:$bg;'>
            <td style='padding:10px 12px;font-size:13px;color:#111827;'>$nombre</td>
            <td style='padding:10px 12px;font-size:13px;color:#6b7280;'>$relacion</td>
            <td style='padding:10px 12px;font-size:13px;color:#d97706;font-weight:700;text-align:right;'>$$monto</td>
        </tr>";
    }

    return "
<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width, initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,\"Segoe UI\",Roboto,sans-serif;background-color:#fffbeb;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:#fffbeb;padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(245,158,11,0.2);border:1px solid #fde68a;'>

    <tr><td style='background:linear-gradient(135deg,#f59e0b 0%,#d97706 100%);padding:48px 20px;text-align:center;'>
        <div style='width:80px;height:80px;margin:0 auto 16px;background:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:40px;box-shadow:0 10px 20px rgba(0,0,0,0.2);'>📋</div>
        <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;'>Resumen Cobro R11</h1>
        <p style='color:#fef3c7;margin:4px 0 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>$fecha</p>
    </td></tr>

    <tr><td style='padding:32px 20px 20px 20px;background:#ffffff;'>
        <div style='text-align:center;margin-bottom:32px;'>
            <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>
                Resumen de deudores R11 para el cobro del <strong>día 1 de " . htmlspecialchars($mes) . "</strong>.
            </p>
        </div>

        <div style='display:flex;gap:16px;margin-bottom:24px;'>
            <div style='background:#fffbeb;border:2px solid #fde68a;border-radius:16px;padding:16px;text-align:center;flex:1;'>
                <p style='color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;margin:0 0 4px 0;'>Deudores</p>
                <p style='color:#d97706;font-size:28px;font-weight:900;margin:0;'>$count</p>
            </div>
            <div style='background:#fffbeb;border:2px solid #fde68a;border-radius:16px;padding:16px;text-align:center;flex:1;'>
                <p style='color:#92400e;font-size:10px;font-weight:700;text-transform:uppercase;margin:0 0 4px 0;'>Total a Cobrar</p>
                <p style='color:#d97706;font-size:28px;font-weight:900;margin:0;'>$$total_fmt</p>
            </div>
        </div>

        <table width='100%' cellpadding='0' cellspacing='0' style='border-radius:16px;overflow:hidden;border:1px solid #fde68a;'>
            <tr style='background:#f59e0b;'>
                <th style='padding:12px;text-align:left;color:#fff;font-size:12px;font-weight:700;text-transform:uppercase;'>Nombre</th>
                <th style='padding:12px;text-align:left;color:#fff;font-size:12px;font-weight:700;text-transform:uppercase;'>Relación</th>
                <th style='padding:12px;text-align:right;color:#fff;font-size:12px;font-weight:700;text-transform:uppercase;'>Deuda</th>
            </tr>
            $rows
        </table>
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
