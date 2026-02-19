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
<body style='margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, &quot;Segoe UI&quot;, Roboto, sans-serif; background-color: #fff7ed;'>
    <table width='100%' cellpadding='0' cellspacing='0' style='background-color: #fff7ed; padding: 10px;'>
        <tr>
            <td align='center'>
                <table width='600' cellpadding='0' cellspacing='0' style='background-color: #ffffff; border-radius: 40px; overflow: hidden; box-shadow: 0 10px 40px -10px rgba(247, 147, 30, 0.2); border: 1px solid #fed7aa;'>
                    
                    <tr>
                        <td style='background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); padding: 48px 20px; text-align: center; position: relative;'>
                            <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width: 80px; height: 80px; margin: 0 auto 16px; display: block; filter: drop-shadow(0 10px 20px rgba(0,0,0,0.2));'>
                            <h1 style='color: #ffffff; margin: 0; font-size: 36px; font-weight: 800; letter-spacing: -0.5px;'>La Ruta 11</h1>
                            <p style='color: #fed7aa; margin: 4px 0 0 0; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 4px;'>Estado de Cuenta</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 32px 20px 20px 20px; background: #ffffff;'>
                            <div style='text-align: center; margin-bottom: 32px;'>
                                <h2 style='color: #111827; margin: 0 0 12px 0; font-size: 24px; font-weight: 800;'>¡Hola, " . htmlspecialchars($user['nombre']) . "! 👋</h2>
                                <p style='color: #6b7280; line-height: 1.6; margin: 0; font-size: 14px; font-weight: 500;'>
                                    Aquí tienes el detalle de tu crédito <strong>RL6</strong>. ¡Gracias por tu confianza!
                                </p>
                            </div>
                            
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td align='center' style='padding-bottom: 32px;'>
                                        <div style='display: inline-block; background: #fed7aa; padding: 8px 24px; border-radius: 999px; margin: 0 8px;'>
                                            <p style='color: #c2410c; margin: 0; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;'>" . htmlspecialchars($user['grado_militar']) . "</p>
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
                            <div style='background: #f9fafb; padding: 24px; border-radius: 32px;'>
                                <h3 style='text-align: center; font-size: 10px; font-weight: 900; color: #9ca3af; text-transform: uppercase; letter-spacing: 3px; margin: 0 0 24px 0;'>Resumen Financiero</h3>
                                
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: #ffffff; border: 2px solid #f8fafc; padding: 20px; border-radius: 24px; text-align: center;'>
                                                <p style='color: #9ca3af; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Cupo Total</p>
                                                <p style='color: #1f2937; font-size: 20px; font-weight: 800; margin: 0;'>$" . number_format($credito_total, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: #ffffff; border: 2px solid #f8fafc; padding: 20px; border-radius: 24px; text-align: center;'>
                                                <p style='color: #fb923c; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Consumido</p>
                                                <p style='color: #ea580c; font-size: 20px; font-weight: 800; margin: 0;'>$" . number_format($credito_usado, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: #ffffff; border: 2px solid #f8fafc; padding: 20px; border-radius: 24px; text-align: center;'>
                                                <p style='color: #34d399; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Disponible</p>
                                                <p style='color: #059669; font-size: 20px; font-weight: 800; margin: 0;'>$" . number_format($credito_disponible, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                        <td width='50%' style='padding: 8px;'>
                                            <div style='background: linear-gradient(135deg, #ea580c 0%, #f97316 100%); padding: 20px; border-radius: 24px; text-align: center; box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);'>
                                                <p style='color: #fed7aa; font-size: 9px; font-weight: 700; text-transform: uppercase; margin: 0 0 4px 0;'>Total a Pagar</p>
                                                <p style='color: #ffffff; font-size: 20px; font-weight: 800; margin: 0;'>$" . number_format($saldo_pagar, 0, ',', '.') . "</p>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 32px 20px;'>
                            <div style='background: #fef2f2; border-radius: 24px; padding: 20px; border: 2px dashed #fecaca;'>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td width='48' style='padding-right: 16px;'>
                                            <div style='background: #ef4444; color: #ffffff; width: 48px; height: 48px; border-radius: 16px; text-align: center; line-height: 48px; font-size: 20px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3);'>🗓️</div>
                                        </td>
                                        <td>
                                            <p style='color: #991b1b; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; margin: 0 0 4px 0;'>Fecha Límite</p>
                                            <p style='color: #7f1d1d; font-size: 18px; font-weight: 700; margin: 0;'>$fecha_vencimiento</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 32px 20px;'>
                            <div style='background: #fff7ed; border: 2px solid #fed7aa; border-radius: 24px; padding: 24px;'>
                                <h3 style='color: #c2410c; margin: 0 0 20px 0; font-size: 16px; font-weight: 800; text-align: center;'>💡 Cómo Pagar tu Crédito</h3>
                                <table width='100%' cellpadding='0' cellspacing='0'>
                                    <tr>
                                        <td style='padding: 10px 0;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td width='32' style='padding-right: 12px; vertical-align: top;'>
                                                        <div style='background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); color: white; width: 32px; height: 32px; border-radius: 50%; text-align: center; line-height: 32px; font-weight: 800; font-size: 14px; box-shadow: 0 2px 8px rgba(247, 147, 30, 0.3);'>1</div>
                                                    </td>
                                                    <td style='vertical-align: top;'>
                                                        <p style='color: #7c2d12; font-size: 14px; line-height: 32px; margin: 0; font-weight: 600;'>Inicia sesión en <strong>app.laruta11.cl</strong></p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 10px 0;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td width='32' style='padding-right: 12px; vertical-align: top;'>
                                                        <div style='background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); color: white; width: 32px; height: 32px; border-radius: 50%; text-align: center; line-height: 32px; font-weight: 800; font-size: 14px; box-shadow: 0 2px 8px rgba(247, 147, 30, 0.3);'>2</div>
                                                    </td>
                                                    <td style='vertical-align: top;'>
                                                        <p style='color: #7c2d12; font-size: 14px; line-height: 32px; margin: 0; font-weight: 600;'>Ve a tu <strong>Perfil</strong> → <strong>Crédito</strong></p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style='padding: 10px 0;'>
                                            <table width='100%' cellpadding='0' cellspacing='0'>
                                                <tr>
                                                    <td width='32' style='padding-right: 12px; vertical-align: top;'>
                                                        <div style='background: linear-gradient(135deg, #FF6B35 0%, #F7931E 100%); color: white; width: 32px; height: 32px; border-radius: 50%; text-align: center; line-height: 32px; font-weight: 800; font-size: 14px; box-shadow: 0 2px 8px rgba(247, 147, 30, 0.3);'>3</div>
                                                    </td>
                                                    <td style='vertical-align: top;'>
                                                        <p style='color: #7c2d12; font-size: 14px; line-height: 32px; margin: 0; font-weight: 600;'>Haz clic en <strong>"Pagar Crédito"</strong></p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='padding: 0 20px 35px 20px;' align='center'>
                            <a href='https://app.laruta11.cl/pagar-credito?user_id=$user_id&monto=$saldo_pagar' 
                               style='display: inline-block; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: #ffffff; text-decoration: none; padding: 20px 40px; border-radius: 32px; font-weight: 800; font-size: clamp(16px, 4vw, 20px); box-shadow: 0 10px 30px rgba(59, 130, 246, 0.3); white-space: nowrap;'>
                                💳 PAGAR MI CRÉDITO
                            </a>
                            <p style='color: #9ca3af; font-size: 11px; margin: 24px 0 0 0; font-weight: 700;'>
                                <span style='display: inline-block; width: 8px; height: 8px; background: #10b981; border-radius: 50%; margin-right: 6px;'></span>
                                PROCESO 100% SEGURO VÍA TUU.CL
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <td style='background-color: #111827; padding: 40px 20px; text-align: center;'>
                            <table width='100%' cellpadding='0' cellspacing='0'>
                                <tr>
                                    <td align='center' style='padding-bottom: 32px;'>
                                        <a href='tel:+56936227422' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; margin: 0 16px; transition: color 0.3s;'>Soporte</a>
                                        <a href='tel:+56945392581' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; margin: 0 16px; transition: color 0.3s;'>Ventas</a>
                                        <a href='https://app.laruta11.cl' style='color: #ffffff; text-decoration: none; font-size: 10px; font-weight: 900; text-transform: uppercase; letter-spacing: 2px; margin: 0 16px; transition: color 0.3s;'>App</a>
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
