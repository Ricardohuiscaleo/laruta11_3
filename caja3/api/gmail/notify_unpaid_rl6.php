<?php
// Notificar pagos RL6 no procesados (unpaid con tuu_message null)
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Buscar órdenes RL6 no procesadas (creadas hace más de 5 minutos)
    $sql = "SELECT 
                t.id, t.order_number, t.user_id, t.customer_name, 
                t.product_price, t.created_at,
                u.email, u.grado_militar, u.unidad_trabajo
            FROM tuu_orders t
            JOIN usuarios u ON t.user_id = u.id
            WHERE t.order_number LIKE 'RL6-%'
            AND t.payment_status = 'unpaid'
            AND (t.tuu_message IS NULL OR t.tuu_message = '')
            AND t.created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            AND t.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY t.created_at DESC";
    
    $stmt = $pdo->query($sql);
    $unpaid_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($unpaid_orders)) {
        echo json_encode([
            'success' => true,
            'message' => 'No hay pagos RL6 pendientes',
            'count' => 0
        ]);
        exit;
    }
    
    // Obtener token de Gmail
    require_once __DIR__ . '/get_token_db.php';
    $token = get_gmail_token_from_db($config);
    
    if (!$token) {
        throw new Exception('Token de Gmail no disponible');
    }
    
    $sent_count = 0;
    
    foreach ($unpaid_orders as $order) {
        // Crear email HTML
        $subject = '⚠️ Pago RL6 No Procesado - La Ruta 11';
        $fecha = date('d/m/Y H:i', strtotime($order['created_at']));
        
        $html = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #f59e0b 0%, #dc2626 100%); padding: 30px; text-align: center;'>
                <h1 style='color: white; margin: 0; font-size: 28px;'>⚠️ Pago No Procesado</h1>
            </div>
            
            <div style='padding: 30px; background: #f9fafb;'>
                <p style='font-size: 16px; color: #374151;'>Hola <strong>{$order['customer_name']}</strong>,</p>
                
                <p style='font-size: 16px; color: #374151;'>Detectamos que intentaste pagar tu crédito RL6 pero el pago no fue procesado.</p>
                
                <div style='background: white; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #f59e0b;'>
                    <h2 style='color: #f59e0b; margin-top: 0;'>Detalles del Intento</h2>
                    <table style='width: 100%; border-collapse: collapse;'>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'>Orden:</td>
                            <td style='padding: 8px 0; color: #111827; font-weight: bold; text-align: right;'>{$order['order_number']}</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'>Monto:</td>
                            <td style='padding: 8px 0; color: #f59e0b; font-weight: bold; text-align: right; font-size: 20px;'>$" . number_format($order['product_price'], 0, ',', '.') . "</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'>Fecha Intento:</td>
                            <td style='padding: 8px 0; color: #111827; text-align: right;'>$fecha</td>
                        </tr>
                        <tr>
                            <td style='padding: 8px 0; color: #6b7280;'>Estado:</td>
                            <td style='padding: 8px 0; color: #dc2626; font-weight: bold; text-align: right;'>NO PROCESADO</td>
                        </tr>
                    </table>
                </div>
                
                <div style='background: #fef3c7; border-radius: 8px; padding: 15px; margin: 20px 0; border: 2px solid #f59e0b;'>
                    <p style='margin: 0; color: #92400e; font-size: 14px;'>
                        <strong>¿Qué pasó?</strong><br>
                        El pago pudo haber sido cancelado o rechazado por Webpay.<br><br>
                        <strong>¿Qué hacer?</strong><br>
                        • Intenta pagar nuevamente desde tu perfil<br>
                        • Verifica que tu tarjeta tenga fondos suficientes<br>
                        • Contacta con nosotros si el problema persiste
                    </p>
                </div>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='https://app.laruta11.cl/pagar-credito' style='background: #16a34a; color: white; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>
                        Intentar Pagar Nuevamente
                    </a>
                </div>
                
                <p style='font-size: 14px; color: #6b7280; margin-top: 30px;'>
                    Si tienes dudas, contáctanos al +56 9 3622 7422 o responde este email.
                </p>
            </div>
            
            <div style='background: #111827; padding: 20px; text-align: center;'>
                <p style='color: #9ca3af; font-size: 12px; margin: 5px 0;'>📍 Yumbel 2629, Arica, Chile</p>
                <p style='color: #9ca3af; font-size: 12px; margin: 5px 0;'>📞 +56 9 3622 7422 | 📧 saboresdelaruta11@gmail.com</p>
                <p style='color: #6b7280; font-size: 11px; margin: 15px 0 0 0;'>© " . date('Y') . " La Ruta 11 SpA</p>
            </div>
        </div>
        ";
        
        // Enviar email con copia a saboresdelaruta11@gmail.com
        $email_data = [
            'raw' => base64_encode(
                "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n" .
                "To: {$order['email']}\r\n" .
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
        
        if ($httpCode === 200) {
            $sent_count++;
            
            // Marcar como notificado (agregar flag para no enviar múltiples veces)
            $update_sql = "UPDATE tuu_orders SET tuu_message = 'Notificado - Pago no procesado' WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$order['id']]);
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Notificaciones enviadas: $sent_count de " . count($unpaid_orders),
        'count' => $sent_count,
        'total' => count($unpaid_orders)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
