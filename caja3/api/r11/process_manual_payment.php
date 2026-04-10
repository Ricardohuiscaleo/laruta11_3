<?php
ob_start();
header('Content-Type: application/json');

// Verificar sesión de admin
require_once __DIR__ . '/../session_config.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(401);
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit;
}

// CORS restringido
$allowed_origins = ['https://app.laruta11.cl', 'https://caja.laruta11.cl'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../gmail/get_token_db.php';

$config = require __DIR__ . '/../../config.php';

$input = json_decode(file_get_contents('php://input'), true);

$user_id = $input['user_id'] ?? null;
$amount = floatval($input['amount'] ?? 0);
$method = $input['method'] ?? 'transfer';
$notes = $input['notes'] ?? '';

if (!$user_id || $amount <= 0) {
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $pdo->beginTransaction();

    $method_text = $method === 'transfer' ? 'Transferencia Bancaria' : 'Efectivo';
    $description = "Pago manual - $method_text" . ($notes ? " - $notes" : "");

    // Insertar transacción de refund en r11_credit_transactions
    $stmt = $pdo->prepare("
        INSERT INTO r11_credit_transactions (user_id, amount, type, description, created_at)
        VALUES (?, ?, 'refund', ?, NOW())
    ");
    $stmt->execute([$user_id, $amount, $description]);

    // Actualizar crédito usado (sin bajar de 0) y fecha de último pago
    $stmt = $pdo->prepare("
        UPDATE usuarios 
        SET credito_r11_usado = GREATEST(0, credito_r11_usado - ?),
            fecha_ultimo_pago_r11 = CURDATE()
        WHERE id = ?
    ");
    $stmt->execute([$amount, $user_id]);

    // Obtener datos del usuario
    $stmt = $pdo->prepare("
        SELECT nombre, email, relacion_r11, limite_credito_r11, credito_r11_usado
        FROM usuarios WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Insertar en tuu_orders para que aparezca en stats
    $order_number = 'R11C-MANUAL-' . time();
    $payment_method_db = $method === 'transfer' ? 'transfer' : 'cash';
    $stmt = $pdo->prepare("
        INSERT INTO tuu_orders (
            order_number, user_id, customer_name, product_name,
            product_price, installment_amount, payment_method, payment_status,
            pagado_con_credito_r11, monto_credito_r11, order_status, status,
            created_at, updated_at
        ) VALUES (?, ?, ?, 'Pago Crédito R11', ?, ?, ?, 'paid', 1, ?, 'completed', 'completed', NOW(), NOW())
    ");
    $stmt->execute([$order_number, $user_id, $user['nombre'], $amount, $amount, $payment_method_db, $amount]);

    $pdo->commit();

    // Enviar email de confirmación
    $token_result = getValidGmailToken();

    if (isset($token_result['error'])) {
        throw new Exception('No se pudo obtener token de Gmail: ' . $token_result['error']);
    }

    $access_token = $token_result['access_token'];

    $credito_total = floatval($user['limite_credito_r11']);
    $credito_usado = floatval($user['credito_r11_usado']);
    $credito_disponible = $credito_total - $credito_usado;

    $html = generateR11PaymentConfirmationEmail($user, $amount, $method_text, $notes, $credito_total, $credito_usado, $credito_disponible);

    $from = (string)($config['gmail_sender_email'] ?: 'saboresdelaruta11@gmail.com');
    $to = $user['email'];
    $cc = 'saboresdelaruta11@gmail.com';
    $subject = "✅ Pago Recibido - Crédito R11 La Ruta 11";

    $message = "From: La Ruta 11 <$from>\r\n";
    $message .= "To: $to\r\n";
    $message .= "Cc: $cc\r\n";
    $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($html));

    $encoded_message = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encoded_message]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code !== 200) {
        error_log("Error sending R11 payment email: " . $response);
        ob_end_clean();
        echo json_encode(['success' => false, 'error' => 'Gmail error ' . $http_code, 'detail' => json_decode($response, true)]);
        exit;
    }

    // Log del email
    try {
        $result = json_decode($response, true);
        $log_stmt = $pdo->prepare("
            INSERT INTO email_logs (
                user_id, email_to, email_type, subject, amount,
                gmail_message_id, gmail_thread_id, status, sent_at
            ) VALUES (?, ?, 'payment_confirmation_r11', ?, ?, ?, ?, 'sent', NOW())
        ");
        $log_stmt->execute([
            $user_id, $to, $subject, $amount,
            $result['id'] ?? null,
            $result['threadId'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error logging R11 email: " . $e->getMessage());
    }

    ob_end_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Pago R11 procesado exitosamente',
        'user' => [
            'nombre' => $user['nombre'],
            'credito_usado' => $credito_usado,
            'credito_disponible' => $credito_disponible
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_end_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generateR11PaymentConfirmationEmail($user, $amount, $method, $notes, $credito_total, $credito_usado, $credito_disponible) {
    $fecha = date('d/m/Y H:i');
    $relacion = htmlspecialchars($user['relacion_r11'] ?? 'Beneficiario');

    return "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, sans-serif; background-color: #fef3c7;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #fef3c7; padding: 10px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 40px; overflow: hidden; box-shadow: 0 10px 40px -10px rgba(245, 158, 11, 0.2); border: 1px solid #fde68a;'>
                    <tr>
                        <td style='background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 48px 20px; text-align: center;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 36px; font-weight: 800;'>¡Pago Recibido!</h1>
                            <p style='color: #fef3c7; margin: 4px 0 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 4px;'>Crédito R11 — $relacion</p>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding: 32px 20px; background: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 32px;'>
                                <h2 style='color: #111827; margin: 0 0 12px 0; font-size: 24px; font-weight: 800;'>¡Gracias, " . htmlspecialchars($user['nombre']) . "!</h2>
                                <p style='color: #6b7280; font-size: 14px; margin: 0;'>Hemos recibido tu pago correctamente. Tu crédito R11 ha sido actualizado.</p>
                            </div>
                            <div style='background: #fffbeb; border: 2px solid #fde68a; border-radius: 24px; padding: 24px; margin-bottom: 24px;'>
                                <h3 style='color: #d97706; margin: 0 0 16px 0; font-size: 16px; font-weight: 800; text-align: center;'>Detalle del Pago</h3>
                                <table width='100%' cellpadding='8' cellspacing='0'>
                                    <tr>
                                        <td style='color: #6b7280; font-size: 14px;'>Monto Pagado:</td>
                                        <td align='right' style='color: #d97706; font-weight: 700; font-size: 18px;'>\$" . number_format($amount, 0, ',', '.') . "</td>
                                    </tr>
                                    <tr>
                                        <td style='color: #6b7280; font-size: 14px;'>Método:</td>
                                        <td align='right' style='color: #111827; font-weight: 600; font-size: 14px;'>$method</td>
                                    </tr>
                                    <tr>
                                        <td style='color: #6b7280; font-size: 14px;'>Fecha:</td>
                                        <td align='right' style='color: #111827; font-weight: 600; font-size: 14px;'>$fecha</td>
                                    </tr>
                                    " . ($notes ? "<tr><td colspan='2' style='color: #6b7280; font-size: 12px; padding-top: 8px; border-top: 1px solid #fde68a;'>Nota: $notes</td></tr>" : "") . "
                                </table>
                            </div>
                            <div style='background: #f9fafb; padding: 24px; border-radius: 24px;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: #ffffff; border: 2px solid #f8fafc; padding: 20px; border-radius: 24px; text-align: center;'>
                                                <p style='color: #9ca3af; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Cupo Total</p>
                                                <p style='color: #1f2937; font-size: 20px; font-weight: 800; margin: 0;'>\$" . number_format($credito_total, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 20px; border-radius: 24px; text-align: center;'>
                                                <p style='color: #fef3c7; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Disponible</p>
                                                <p style='color: #ffffff; font-size: 20px; font-weight: 800; margin: 0;'>\$" . number_format($credito_disponible, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style='background-color: #111827; padding: 40px 20px; text-align: center;'>
                            <p style='color: #6b7280; margin: 0; font-size: 11px; line-height: 1.8;'>
                                Yumbel 2629, Arica, Chile<br>
                                <span style='color: #4b5563;'>© " . date('Y') . " La Ruta 11 SpA.</span>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    ";
}
