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
    
    // Crear email HTML
    $subject = '✅ Pago de Crédito RL6 Confirmado - La Ruta 11';
    $fecha = date('d/m/Y H:i');
    
    $html = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #f97316 0%, #dc2626 100%); padding: 30px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 28px;'>🎉 ¡Pago Confirmado!</h1>
        </div>
        
        <div style='padding: 30px; background: #f9fafb;'>
            <p style='font-size: 16px; color: #374151;'>Hola <strong>{$user['nombre']}</strong>,</p>
            
            <p style='font-size: 16px; color: #374151;'>Tu pago de crédito RL6 ha sido procesado exitosamente.</p>
            
            <div style='background: white; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #16a34a;'>
                <h2 style='color: #16a34a; margin-top: 0;'>Detalles del Pago</h2>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr>
                        <td style='padding: 8px 0; color: #6b7280;'>Orden:</td>
                        <td style='padding: 8px 0; color: #111827; font-weight: bold; text-align: right;'>$order_id</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #6b7280;'>Monto Pagado:</td>
                        <td style='padding: 8px 0; color: #16a34a; font-weight: bold; text-align: right; font-size: 20px;'>$" . number_format($amount, 0, ',', '.') . "</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #6b7280;'>Fecha:</td>
                        <td style='padding: 8px 0; color: #111827; text-align: right;'>$fecha</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #6b7280;'>Grado:</td>
                        <td style='padding: 8px 0; color: #111827; text-align: right;'>{$user['grado_militar']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #6b7280;'>Unidad:</td>
                        <td style='padding: 8px 0; color: #111827; text-align: right;'>{$user['unidad_trabajo']}</td>
                    </tr>
                </table>
            </div>
            
            <div style='background: #dbeafe; border-radius: 8px; padding: 15px; margin: 20px 0;'>
                <p style='margin: 0; color: #1e40af; font-size: 14px;'>
                    ✅ Tu crédito ha sido reestablecido completamente<br>
                    ✅ Ya puedes volver a usar tu línea de crédito RL6
                </p>
            </div>
            
            <p style='font-size: 14px; color: #6b7280; margin-top: 30px;'>
                Gracias por tu pago puntual. Si tienes alguna consulta, no dudes en contactarnos.
            </p>
        </div>
        
        <div style='background: #111827; padding: 20px; text-align: center;'>
            <p style='color: #9ca3af; font-size: 12px; margin: 5px 0;'>📍 Yumbel 2629, Arica, Chile</p>
            <p style='color: #9ca3af; font-size: 12px; margin: 5px 0;'>📞 +56 9 3622 7422 | 📧 saboresdelaruta11@gmail.com</p>
            <p style='color: #6b7280; font-size: 11px; margin: 15px 0 0 0;'>© " . date('Y') . " La Ruta 11 SpA</p>
        </div>
    </div>
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
        // Registrar email fallido
        $log_sql = "INSERT INTO email_logs (user_id, email_to, email_type, subject, order_id, amount, status, error_message) 
                    VALUES (?, ?, 'payment_confirmation', ?, ?, ?, 'failed', ?)";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$user_id, $user['email'], $subject, $order_id, $amount, 'HTTP ' . $httpCode]);
        
        throw new Exception('Error enviando email');
    }
    
    // Decodificar respuesta de Gmail para obtener message_id
    $gmail_response = json_decode($response, true);
    $message_id = $gmail_response['id'] ?? null;
    $thread_id = $gmail_response['threadId'] ?? null;
    
    // Registrar email exitoso
    $log_sql = "INSERT INTO email_logs (user_id, email_to, email_type, subject, order_id, amount, gmail_message_id, gmail_thread_id, status) 
                VALUES (?, ?, 'payment_confirmation', ?, ?, ?, ?, ?, 'sent')";
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute([$user_id, $user['email'], $subject, $order_id, $amount, $message_id, $thread_id]);
    
    echo json_encode(['success' => true, 'message_id' => $message_id]);
    
} catch (Exception $e) {
    // Registrar error si tenemos datos del usuario
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
