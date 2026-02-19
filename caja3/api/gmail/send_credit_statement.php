<?php
header('Content-Type: application/json');
require_once __DIR__ . '/get_token_db.php';

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
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #f4f4f4; padding: 5px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                    
                    <tr>
                        <td style='background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%); padding: 30px 20px; text-align: center;'>
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td align='center'>
                                        <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width: 50px; height: 50px; vertical-align: middle; margin-right: 15px;'>
                                        <div style='display: inline-block; vertical-align: middle; text-align: left;'>
                                            <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: bold; line-height: 1.2;'>La Ruta 11</h1>
                                            <p style='color: rgba(255,255,255,0.95); margin: 0; font-size: 13px; font-weight: 500;'>Estado de Cuenta - Crédito RL6</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 35px 20px 25px 20px;'>
                            <h2 style='color: #1f2937; margin: 0 0 8px 0; font-size: 22px;'>Hola, " . htmlspecialchars($user['nombre']) . " 👋</h2>
                            <p style='color: #6b7280; line-height: 1.6; margin: 0 0 20px 0; font-size: 15px;'>
                                Te enviamos el detalle de tu crédito La Ruta 11. Gracias por confiar en nosotros.
                            </p>
                            <div style='background-color: #f9fafb; border-left: 3px solid #ff6b35; padding: 12px 15px; border-radius: 4px;'>
                                <p style='color: #4b5563; margin: 0; font-size: 14px; line-height: 1.5;'>
                                    <strong style='color: #1f2937;'>" . htmlspecialchars($user['grado_militar']) . "</strong><br>
                                    " . htmlspecialchars($user['unidad_trabajo']) . "
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 25px 20px;'>
                            <div style='background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);'>
                                <h3 style='color: #111827; margin: 0 0 20px 0; font-size: 15px; font-weight: 700; letter-spacing: -0.01em;'>📊 Resumen de Cuenta</h3>
                                
                                <div style='width: 100%;'>
                                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;'>
                                        <span style='color: #6b7280; font-size: 13px; font-weight: 500;'>Crédito Total</span>
                                        <span style='color: #111827; font-weight: 600; font-size: 15px;'>$" . number_format($credito_total, 0, ',', '.') . "</span>
                                    </div>
                                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;'>
                                        <span style='color: #6b7280; font-size: 13px; font-weight: 500;'>Consumido</span>
                                        <span style='color: #111827; font-weight: 600; font-size: 15px;'>$" . number_format($credito_usado, 0, ',', '.') . "</span>
                                    </div>
                                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid #f3f4f6;'>
                                        <span style='color: #6b7280; font-size: 13px; font-weight: 500;'>Disponible</span>
                                        <span style='color: #059669; font-weight: 600; font-size: 15px;'>$" . number_format($credito_disponible, 0, ',', '.') . "</span>
                                    </div>
                                    <div style='display: flex; justify-content: space-between; align-items: center; padding: 16px 0 0 0;'>
                                        <span style='color: #111827; font-size: 14px; font-weight: 700;'>Saldo a Pagar</span>
                                        <span style='color: #dc2626; font-weight: 700; font-size: 22px; letter-spacing: -0.02em;'>$" . number_format($saldo_pagar, 0, ',', '.') . "</span>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 25px 20px;'>
                            <div style='background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; padding: 16px 18px; border-radius: 6px;'>
                                <p style='margin: 0; color: #92400e; font-size: 14px; font-weight: 500;'>
                                    📅 <strong>Fecha de Vencimiento:</strong> $fecha_vencimiento
                                </p>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 25px 20px;'>
                            <div style='background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border: 1px solid #93c5fd; border-radius: 8px; padding: 20px;'>
                                <h3 style='color: #1e40af; margin: 0 0 15px 0; font-size: 16px; font-weight: 600;'>💡 Cómo Pagar tu Crédito</h3>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td style='padding: 8px 0;'>
                                            <div style='display: flex; align-items: start;'>
                                                <span style='background: #3b82f6; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-weight: bold; font-size: 12px; margin-right: 12px; flex-shrink: 0;'>1</span>
                                                <span style='color: #1e40af; font-size: 14px; line-height: 24px;'>Inicia sesión en tu cuenta de <strong>app.laruta11.cl</strong></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px 0;'>
                                            <div style='display: flex; align-items: start;'>
                                                <span style='background: #3b82f6; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-weight: bold; font-size: 12px; margin-right: 12px; flex-shrink: 0;'>2</span>
                                                <span style='color: #1e40af; font-size: 14px; line-height: 24px;'>Ve a tu <strong>Perfil</strong> y selecciona <strong>&quot;Crédito&quot;</strong></span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 8px 0;'>
                                            <div style='display: flex; align-items: start;'>
                                                <span style='background: #3b82f6; color: white; width: 24px; height: 24px; border-radius: 50%; display: inline-block; text-align: center; line-height: 24px; font-weight: bold; font-size: 12px; margin-right: 12px; flex-shrink: 0;'>3</span>
                                                <span style='color: #1e40af; font-size: 14px; line-height: 24px;'>Haz clic en <strong>&quot;Pagar Crédito&quot;</strong></span>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 35px 20px;' align='center'>
                            <p style='color: #6b7280; font-size: 14px; margin: 0 0 15px 0;'>
                                ✨ <strong>¿Ya estás logueado?</strong> Haz clic aquí:
                            </p>
                            <a href='https://app.laruta11.cl/pagar-credito?user_id=$user_id&monto=$saldo_pagar' 
                               style='display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; padding: 18px 60px; border-radius: 8px; font-weight: bold; font-size: 18px; box-shadow: 0 4px 14px rgba(59,130,246,0.4);'>
                                💳 Pagar Ahora
                            </a>
                            <p style='color: #9ca3af; font-size: 12px; margin: 15px 0 0 0;'>
                                🔒 Pago seguro procesado por TUU.cl
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #f9fafb; padding: 25px 30px; text-align: center; border-top: 1px solid #e5e7eb;'>
                            <p style='color: #6b7280; margin: 0 0 8px 0; font-size: 13px; line-height: 1.6;'>
                                📍 Yumbel 2629, Arica, Chile<br>
                                📞 Ventas: <a href='tel:+56936227422' style='color: #ff6b35; text-decoration: none;'>+56 9 3622 7422</a> | 
                                🛠️ Soporte: <a href='tel:+56945392581' style='color: #ff6b35; text-decoration: none;'>+56 9 4539 2581</a><br>
                                📧 <a href='mailto:saboresdelaruta11@gmail.com' style='color: #ff6b35; text-decoration: none;'>saboresdelaruta11@gmail.com</a><br>
                                <a href='https://app.laruta11.cl' style='color: #ff6b35; text-decoration: none; font-weight: 500;'>app.laruta11.cl</a>
                            </p>
                            <p style='color: #d1d5db; margin: 15px 0 0 0; font-size: 11px;'>
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

$message = "From: La Ruta 11 <$from>\r\n";
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
