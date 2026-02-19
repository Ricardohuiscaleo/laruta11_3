<?php
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $user_id = $input['user_id'] ?? null;
    $order_id = $input['order_id'] ?? null;
    $amount = $input['amount'] ?? null;
    
    if (!$user_id || !$order_id || !$amount) {
        throw new Exception('Datos incompletos');
    }
    
    // Obtener datos del usuario
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $user_sql = "SELECT nombre, email, grado_militar, unidad_trabajo FROM usuarios WHERE id = ?";
    $user_stmt = $pdo->prepare($user_sql);
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Usuario no encontrado');
    }
    
    // Obtener token de Gmail
    require_once __DIR__ . '/get_token_db.php';
    $token_result = getValidGmailToken();
    
    if (isset($token_result['error'])) {
        throw new Exception($token_result['error']);
    }
    
    $token = $token_result['access_token'];
    
    // Crear email HTML con diseño moderno
    $subject = '✅ Pago de Crédito RL6 Confirmado - La Ruta 11';
    date_default_timezone_set('America/Santiago');
    $fecha = date('d/m/Y H:i');
    
    $html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, sans-serif; background-color: #ecfdf5;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #ecfdf5; padding: 10px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 40px; overflow: hidden; box-shadow: 0 10px 40px -10px rgba(16, 185, 129, 0.2); border: 1px solid #a7f3d0;'>
                    
                    <tr>
                        <td style='background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 48px 20px; text-align: center;'>
                            <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width: 80px; height: 80px; margin: 0 auto 16px; display: block; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 36px; font-weight: 800; letter-spacing: -0.5px;'>¡Pago Confirmado!</h1>
                            <p style='color: #d1fae5; margin: 4px 0 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 4px;'>Crédito RL6</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 32px 20px 20px 20px; background: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 32px;'>
                                <h2 style='color: #111827; margin: 0 0 12px 0; font-size: 24px; font-weight: 800;'>¡Hola, " . htmlspecialchars($user['nombre']) . "! 🎉</h2>
                                <p style='color: #6b7280; line-height: 1.6; margin: 0; font-size: 14px; font-weight: 500;'>
                                    Tu pago de crédito <strong>RL6</strong> ha sido procesado exitosamente.
                                </p>
                            </div>
                            
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td align='center' style='padding-bottom: 32px;'>
                                        <div style='display: inline-block; background: #d1fae5; padding: 8px 24px; border-radius: 999px; margin: 0 8px;'>
                                            <p style='color: #065f46; margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;'>" . htmlspecialchars($user['grado_militar']) . "</p>
                                        </div>
                                        <div style='display: inline-block; background: #f3f4f6; padding: 8px 24px; border-radius: 999px; margin: 0 8px;'>
                                            <p style='color: #4b5563; margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;'>Unidad " . htmlspecialchars($user['unidad_trabajo']) . "</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 32px 20px;'>
                            <div style='background: #f0fdf4; border: 2px solid #bbf7d0; border-radius: 32px; padding: 24px;'>
                                <h3 style='text-align: center; font-size: 10px; font-weight: 900; color: #16a34a; text-transform: uppercase; letter-spacing: 3px; margin: 0 0 24px 0;'>Detalles del Pago</h3>
                                
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td style='padding: 12px 0; border-bottom: 1px solid #d1fae5;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #6b7280; font-size: 13px; font-weight: 600;'>Orden:</td>
                                                    <td style='color: #111827; font-size: 13px; font-weight: 700; text-align: right;'>$order_id</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 12px 0; border-bottom: 1px solid #d1fae5;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #6b7280; font-size: 13px; font-weight: 600;'>Monto Pagado:</td>
                                                    <td style='color: #10b981; font-size: 24px; font-weight: 800; text-align: right;'>$" . number_format($amount, 0, ',', '.') . "</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 12px 0; border-bottom: 1px solid #d1fae5;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #6b7280; font-size: 13px; font-weight: 600;'>Fecha:</td>
                                                    <td style='color: #111827; font-size: 13px; font-weight: 700; text-align: right;'>$fecha</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 12px 0; border-bottom: 1px solid #d1fae5;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #6b7280; font-size: 13px; font-weight: 600;'>Grado:</td>
                                                    <td style='color: #111827; font-size: 13px; font-weight: 700; text-align: right;'>{$user['grado_militar']}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 12px 0;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td style='color: #6b7280; font-size: 13px; font-weight: 600;'>Unidad:</td>
                                                    <td style='color: #111827; font-size: 13px; font-weight: 700; text-align: right;'>{$user['unidad_trabajo']}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 32px 20px;'>
                            <div style='background: #dbeafe; border-radius: 24px; padding: 20px; border: 2px solid #93c5fd;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td width='48' style='padding-right: 16px;'>
                                            <div style='background: #3b82f6; color: #ffffff; width: 48px; height: 48px; border-radius: 16px; text-align: center; line-height: 48px; font-size: 20px; box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);'>✅</div>
                                        </td>
                                        <td>
                                            <p style='color: #1e40af; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 4px 0;'>Estado del Crédito</p>
                                            <p style='color: #1e3a8a; font-size: 14px; font-weight: 700; margin: 0; line-height: 1.4;'>Tu crédito ha sido reestablecido completamente. Ya puedes volver a usar tu línea de crédito RL6.</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 35px 20px;' align='center'>
                            <a href='https://app.laruta11.cl' 
                               style='display: inline-block; background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); color: #ffffff; text-decoration: none; padding: 20px 40px; border-radius: 32px; font-weight: 800; font-size: 18px; box-shadow: 0 10px 30px rgba(247, 147, 30, 0.3); white-space: nowrap;'>
                                IR A LA APP
                            </a>
                            <p style='color: #9ca3af; font-size: 11px; margin: 24px 0 0 0; font-weight: 700;'>
                                Gracias por tu pago puntual
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #111827; padding: 40px 20px; text-align: center;'>
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td width='33.33%' align='center' style='padding-bottom: 32px;'>
                                        <a href='tel:+56936227422' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px;'>Soporte</a>
                                    </td>
                                    <td width='33.33%' align='center' style='padding-bottom: 32px;'>
                                        <a href='tel:+56945392581' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px;'>Ventas</a>
                                    </td>
                                    <td width='33.33%' align='center' style='padding-bottom: 32px;'>
                                        <a href='https://app.laruta11.cl' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px;'>App</a>
                                    </td>
                                </tr>
                            </table>
                            <p style='color: #6b7280; margin: 0; font-size: 11px; line-height: 1.8; font-weight: 500;'>
                                Yumbel 2629, Arica, Chile<br>
                                <span style='color: #4b5563;'>© " . date('Y') . " La Ruta 11 SpA. Sabores con historia.</span>
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
    
    // Enviar email
    $email_data = [
        'raw' => base64_encode(
            "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n" .
            "To: {$user['email']}\r\n" .
            "Cc: saboresdelaruta11@gmail.com\r\n" .
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
            $html
        )
    ];
    
    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($email_data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        $log_sql = "INSERT INTO email_logs (user_id, email_to, email_type, subject, order_id, amount, status, error_message) 
                    VALUES (?, ?, 'payment_confirmation', ?, ?, ?, 'failed', ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$user_id, $user['email'], $subject, $order_id, $amount, 'HTTP ' . $httpCode]);
        
        throw new Exception('Error enviando email');
    }
    
    $gmail_response = json_decode($response, true);
    $message_id = $gmail_response['id'] ?? null;
    $thread_id = $gmail_response['threadId'] ?? null;
    
    $log_sql = "INSERT INTO email_logs (user_id, email_to, email_type, subject, order_id, amount, gmail_message_id, gmail_thread_id, status) 
                VALUES (?, ?, 'payment_confirmation', ?, ?, ?, ?, ?, 'sent')";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([$user_id, $user['email'], $subject, $order_id, $amount, $message_id, $thread_id]);
    
    echo json_encode(['success' => true, 'message_id' => $message_id]);
    
} catch (Exception $e) {
    if (isset($pdo) && isset($user_id) && isset($order_id)) {
        try {
            $log_sql = "INSERT INTO email_logs (user_id, email_to, email_type, subject, order_id, amount, status, error_message) 
                        VALUES (?, ?, 'payment_confirmation', ?, ?, ?, 'failed', ?)";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([$user_id, $user['email'] ?? 'unknown', $subject ?? 'Payment Confirmation', $order_id, $amount ?? 0, $e->getMessage()]);
        } catch (Exception $log_error) {
            error_log("Error logging failed email: " . $log_error->getMessage());
        }
    }
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
