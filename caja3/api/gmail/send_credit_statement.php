<?php
header('Content-Type: application/json');
require_once __DIR__ . '/get_token.php';

$config = require_once __DIR__ . '/../../config.php';

// Obtener user_id
$user_id = $_GET['user_id'] ?? $_POST['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'error' => 'user_id requerido']);
    exit;
}

// Conectar a base de datos
$conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit;
}

// Obtener datos del usuario y su crédito
$stmt = $conn->prepare("
    SELECT 
        u.id,
        u.nombre,
        u.email,
        u.es_militar_rl6,
        u.credito_aprobado,
        u.limite_credito,
        u.credito_usado,
        u.grado_militar,
        u.unidad_trabajo,
        u.fecha_aprobacion_rl6
    FROM usuarios u
    WHERE u.id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

if (!$user['es_militar_rl6'] || !$user['credito_aprobado']) {
    echo json_encode(['success' => false, 'error' => 'Usuario no tiene crédito RL6 aprobado']);
    exit;
}

// Calcular valores
$credito_total = floatval($user['limite_credito']);
$credito_usado = floatval($user['credito_usado']);
$credito_disponible = $credito_total - $credito_usado;
$saldo_pagar = $credito_usado;

// Fecha de vencimiento (día 21 del mes actual)
$meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mes_actual = $meses[date('n') - 1];
$anio_actual = date('Y');
$fecha_vencimiento = "21 de $mes_actual, $anio_actual";

// Generar HTML del email
$html = "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
</head>
<body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 20px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    
                    <tr>
                        <td style='background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); padding: 30px; text-align: center;'>
                            <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width: 80px; height: 80px; margin: 0 auto 15px;'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 28px;'>La Ruta 11</h1>
                            <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 16px;'>Estado de Cuenta - Crédito RL6</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 30px;'>
                            <h2 style='color: #333333; margin: 0 0 15px 0;'>Hola, " . htmlspecialchars($user['nombre']) . " 👋</h2>
                            <p style='color: #666666; line-height: 1.6; margin: 0;'>
                                Te enviamos el detalle de tu crédito La Ruta 11. Gracias por confiar en nosotros.
                            </p>
                            <p style='color: #666666; line-height: 1.6; margin: 10px 0 0 0; font-size: 14px;'>
                                <strong>Grado:</strong> " . htmlspecialchars($user['grado_militar']) . "<br>
                                <strong>Unidad:</strong> " . htmlspecialchars($user['unidad_trabajo']) . "
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f8f9fa; border-radius: 8px; overflow: hidden;'>
                                <tr>
                                    <td style='padding: 20px;'>
                                        <h3 style='color: #333333; margin: 0 0 15px 0; font-size: 18px;'>📊 Resumen de Cuenta</h3>
                                        
                                        <table width='100%' cellpadding='8' cellspacing='0'>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Crédito Total:</td>
                                                <td align='right' style='color: #333333; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$" . number_format($credito_total, 0, ',', '.') . "</td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Consumido:</td>
                                                <td align='right' style='color: #333333; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$" . number_format($credito_usado, 0, ',', '.') . "</td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; border-bottom: 1px solid #e0e0e0;'>Disponible:</td>
                                                <td align='right' style='color: #22c55e; font-weight: bold; border-bottom: 1px solid #e0e0e0;'>$" . number_format($credito_disponible, 0, ',', '.') . "</td>
                                            </tr>
                                            <tr>
                                                <td style='color: #666666; padding-top: 10px;'>Saldo a Pagar:</td>
                                                <td align='right' style='color: #ef4444; font-weight: bold; font-size: 20px; padding-top: 10px;'>$" . number_format($saldo_pagar, 0, ',', '.') . "</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;'>
                            <div style='background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; border-radius: 4px;'>
                                <p style='margin: 0; color: #856404;'>
                                    <strong>📅 Fecha de Vencimiento:</strong> $fecha_vencimiento
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 30px 30px 30px;' align='center'>
                            <a href='https://app.laruta11.cl/estado-cuenta?user_id=$user_id' 
                               style='display: inline-block; background: #6b7280; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: bold; font-size: 14px; margin-bottom: 10px;'>
                                📊 Ver Estado de Cuenta Detallado
                            </a><br>
                            <a href='https://app.laruta11.cl/pagar-credito?user_id=$user_id&monto=$saldo_pagar' 
                               style='display: inline-block; background: #0074D9; color: #ffffff; text-decoration: none; padding: 15px 40px; border-radius: 8px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);'>
                                💳 Pagar Ahora
                            </a>
                            <p style='color: #999999; font-size: 12px; margin: 15px 0 0 0;'>
                                Pago seguro procesado por TUU.cl
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e0e0e0;'>
                            <p style='color: #999999; margin: 0; font-size: 12px;'>
                                📍 Yumbel 2629, Arica, Chile<br>
                                📞 Ventas: +56 9 3622 7422 | 🛠️ Soporte: +56 9 4539 2581<br>
                                📧 saboresdelaruta11@gmail.com<br>
                                <a href='https://app.laruta11.cl' style='color: #ff6b35; text-decoration: none;'>app.laruta11.cl</a>
                            </p>
                            <p style='color: #cccccc; margin: 15px 0 0 0; font-size: 11px;'>
                                © " . date('Y') . " La Ruta 11 SpA. Todos los derechos reservados.
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

// Obtener token válido
$token_result = getValidGmailToken();

if (isset($token_result['error'])) {
    echo json_encode(['success' => false, 'error' => $token_result['error']]);
    exit;
}

$access_token = $token_result['access_token'];

// Crear mensaje
$from = $config['gmail_sender_email'];
$to = $user['email'];
$subject = "💳 Estado de Cuenta - Crédito La Ruta 11";

$message = "From: $from\r\n";
$message .= "To: $to\r\n";
$message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n";
$message .= chunk_split(base64_encode($html));

// Codificar en base64url
$encoded_message = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

// Enviar email via Gmail API
$url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
$data = json_encode(['raw' => $encoded_message]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code === 200) {
    echo json_encode([
        'success' => true,
        'message' => 'Email enviado correctamente',
        'message_id' => $result['id'],
        'to' => $to,
        'user' => [
            'nombre' => $user['nombre'],
            'credito_total' => $credito_total,
            'credito_usado' => $credito_usado,
            'saldo_pagar' => $saldo_pagar
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar email',
        'details' => $result
    ]);
}

$conn->close();
?>
