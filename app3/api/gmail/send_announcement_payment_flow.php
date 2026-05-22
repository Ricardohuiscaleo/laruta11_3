<?php
/**
 * Envía anuncio del nuevo flujo de pago por transferencia a todos los clientes RL6.
 * Uso: php send_announcement_payment_flow.php
 * O via web: ?send=1 (procesa en lotes de 10)
 */

header('Content-Type: application/json; charset=utf-8');

$config_paths = [
    __DIR__ . '/../../config.php',
    __DIR__ . '/../config.php',
];
$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) { $config = require $path; break; }
}
if (!$config) { die(json_encode(['error' => 'Config no encontrado'])); }

$pdo = new PDO(
    "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
    $config['app_db_user'], $config['app_db_pass'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

date_default_timezone_set('America/Santiago');

$log = [];

// Obtener todos los usuarios RL6 con crédito aprobado y email
$users = $pdo->query("
    SELECT id, nombre, email, grado_militar, unidad_trabajo, limite_credito, credito_usado
    FROM usuarios
    WHERE es_militar_rl6 = 1 AND credito_aprobado = 1 AND email IS NOT NULL AND email != ''
    ORDER BY id
")->fetchAll(PDO::FETCH_ASSOC);

$total = count($users);
$log[] = "Total usuarios RL6 con email: {$total}";

// Obtener token Gmail
require_once __DIR__ . '/get_token_db.php';
$token_result = getValidGmailToken();
if (isset($token_result['error'])) {
    die(json_encode(['error' => $token_result['error'], 'log' => $log]));
}
$token = $token_result['access_token'];

$subject = '🆕 Nuevo sistema de pago para tu crédito RL6 - La Ruta 11';

$sent = 0;
$failed = 0;
$failed_names = [];

foreach ($users as $user) {
    $nombre = htmlspecialchars($user['nombre']);
    $grado = htmlspecialchars($user['grado_militar'] ?? '');
    $unidad = htmlspecialchars($user['unidad_trabajo'] ?? '');
    $uid = $user['id'];
    $deuda = number_format((float)$user['credito_usado'], 0, ',', '.');
    $limite = number_format((float)$user['limite_credito'], 0, ',', '.');

    $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:#eff6ff;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;padding:10px;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(59,130,246,0.2);border:1px solid #bfdbfe;">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);padding:48px 20px;text-align:center;">
                            <img src="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/menu/logo.png" alt="La Ruta 11" style="width:80px;height:80px;margin:0 auto 16px;display:block;filter:drop-shadow(0 10px 20px rgba(0,0,0,0.2));border-radius:50%;">
                            <h1 style="color:#ffffff;margin:0;font-size:28px;font-weight:800;letter-spacing:-0.5px;">¡Novedades en tu Crédito RL6!</h1>
                            <p style="color:#bfdbfe;margin:8px 0 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;">Nuevo Sistema de Pago</p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding:32px 20px 20px 20px;background:#ffffff;">
                            <div style="text-align:center;margin-bottom:32px;">
                                <h2 style="color:#111827;margin:0 0 12px 0;font-size:24px;font-weight:800;">¡Hola, {$nombre}! 👋</h2>
                                <p style="color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;">
                                    Hemos mejorado la forma de pagar tu crédito <strong>RL6</strong>. 
                                    Ahora puedes pagar mediante <strong>transferencia bancaria</strong> de forma rápida y segura.
                                </p>
                            </div>
                            
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding-bottom:24px;">
                                        <div style="display:inline-block;background:#dbeafe;padding:8px 24px;border-radius:999px;margin:0 8px;">
                                            <p style="color:#1e40af;margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">{$grado}</p>
                                        </div>
                                        <div style="display:inline-block;background:#f3f4f6;padding:8px 24px;border-radius:999px;margin:0 8px;">
                                            <p style="color:#4b5563;margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Unidad {$unidad}</p>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Resumen de crédito -->
                    <tr>
                        <td style="padding:0 20px 24px 20px;">
                            <div style="background:#f8fafc;border:2px solid #e2e8f0;border-radius:24px;padding:20px;">
                                <h3 style="text-align:center;font-size:10px;font-weight:900;color:#64748b;text-transform:uppercase;letter-spacing:3px;margin:0 0 20px 0;">Tu Crédito RL6</h3>
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="width:50%;padding:8px 0;text-align:center;">
                                            <p style="color:#6b7280;font-size:11px;font-weight:600;margin:0 0 4px 0;">Límite</p>
                                            <p style="color:#111827;font-size:20px;font-weight:800;margin:0;">\${$limite}</p>
                                        </td>
                                        <td style="width:50%;padding:8px 0;text-align:center;">
                                            <p style="color:#6b7280;font-size:11px;font-weight:600;margin:0 0 4px 0;">Saldo a Pagar</p>
                                            <p style="color:#ef4444;font-size:20px;font-weight:800;margin:0;">\${$deuda}</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>

                    <!-- Cómo pagar -->
                    <tr>
                        <td style="padding:0 20px 24px 20px;">
                            <div style="background:#eff6ff;border:2px solid #bfdbfe;border-radius:24px;padding:24px;">
                                <h3 style="font-size:10px;font-weight:900;color:#1d4ed8;text-transform:uppercase;letter-spacing:3px;margin:0 0 20px 0;text-align:center;">📱 ¿Cómo pagar?</h3>
                                
                                <table width="100%" cellpadding="0" cellspacing="0">
                                    <tr>
                                        <td style="padding-bottom:16px;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="40" style="vertical-align:top;">
                                                        <div style="background:#2563eb;width:32px;height:32px;border-radius:999px;display:flex;align-items:center;justify-content:center;">
                                                            <span style="color:#fff;font-size:14px;font-weight:800;display:block;text-align:center;">1</span>
                                                        </div>
                                                    </td>
                                                    <td style="padding-left:12px;">
                                                        <p style="color:#111827;font-size:14px;font-weight:700;margin:0 0 2px 0;">Ingresa a tu estado de cuenta</p>
                                                        <p style="color:#6b7280;font-size:13px;margin:0;line-height:1.4;">
                                                            Entra a <strong>app.laruta11.cl/pagar-credito</strong> con tu usuario
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom:16px;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="40" style="vertical-align:top;">
                                                        <div style="background:#2563eb;width:32px;height:32px;border-radius:999px;display:flex;align-items:center;justify-content:center;">
                                                            <span style="color:#fff;font-size:14px;font-weight:800;display:block;text-align:center;">2</span>
                                                        </div>
                                                    </td>
                                                    <td style="padding-left:12px;">
                                                        <p style="color:#111827;font-size:14px;font-weight:700;margin:0 0 2px 0;">Transfiere al BCI</p>
                                                        <p style="color:#6b7280;font-size:13px;margin:0;line-height:1.4;">
                                                            Titular: <strong>La Ruta once Spa</strong> · RUT 78.194.739-3<br>
                                                            Cuenta Corriente: <strong>97618110</strong>
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding-bottom:0;">
                                            <table width="100%" cellpadding="0" cellspacing="0">
                                                <tr>
                                                    <td width="40" style="vertical-align:top;">
                                                        <div style="background:#2563eb;width:32px;height:32px;border-radius:999px;display:flex;align-items:center;justify-content:center;">
                                                            <span style="color:#fff;font-size:14px;font-weight:800;display:block;text-align:center;">3</span>
                                                        </div>
                                                    </td>
                                                    <td style="padding-left:12px;">
                                                        <p style="color:#111827;font-size:14px;font-weight:700;margin:0 0 2px 0;">Sube tu comprobante</p>
                                                        <p style="color:#6b7280;font-size:13px;margin:0;line-height:1.4;">
                                                            En la misma página sube la foto del comprobante y lo revisaremos al instante ✅
                                                        </p>
                                                    </td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- CTA Button -->
                    <tr>
                        <td style="padding:0 20px 24px 20px;text-align:center;">
                            <a href="https://app.laruta11.cl/pagar-credito/?user_id={$uid}" style="display:inline-block;background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%);color:#ffffff;text-decoration:none;font-weight:800;font-size:16px;padding:16px 48px;border-radius:999px;box-shadow:0 4px 14px -4px rgba(37,99,235,0.5);">
                                IR A PAGAR MI CRÉDITO
                            </a>
                            <p style="color:#9ca3af;font-size:11px;margin:12px 0 0 0;">
                                O ingresa manualmente a: app.laruta11.cl/pagar-credito
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background:#111827;padding:32px 20px;">
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="text-align:center;padding-bottom:16px;">
                                        <img src="https://pub-d6bf1ac3bcb0465cabadb9eeab426a65.r2.dev/menu/logo.png" alt="La Ruta 11" style="width:48px;height:48px;display:inline-block;border-radius:50%;">
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;">
                                        <p style="color:#9ca3af;font-size:12px;margin:0 0 4px 0;">📞 <strong style="color:#f3f4f6;">Ventas:</strong> +56 9 3622 7422</p>
                                        <p style="color:#9ca3af;font-size:12px;margin:0 0 4px 0;">🛠️ <strong style="color:#f3f4f6;">Soporte:</strong> +56 9 4539 2581</p>
                                        <p style="color:#9ca3af;font-size:12px;margin:0;">📧 saboresdelaruta11@gmail.com</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;padding-top:16px;">
                                        <a href="https://app.laruta11.cl" style="color:#60a5fa;font-size:12px;text-decoration:none;margin:0 8px;">🌐 App</a>
                                        <a href="https://mi.laruta11.cl" style="color:#60a5fa;font-size:12px;text-decoration:none;margin:0 8px;">🔒 Admin</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align:center;padding-top:16px;">
                                        <p style="color:#4b5563;font-size:11px;margin:0;">© 2026 La Ruta 11 SpA · Yumbel 2629, Arica, Chile</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

    // Enviar via Gmail API
    $boundary = uniqid('boundary_');
    $message = "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n";
    $message .= "To: {$user['email']}\r\n";
    $message .= "Subject: {$subject}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "\r\n";
    $message .= $html;

    $encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['raw' => $encoded]),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $sent++;
        $log[] = "✅ {$user['email']} - {$user['nombre']}";
    } else {
        $failed++;
        $failed_names[] = $user['nombre'];
        $log[] = "❌ {$user['email']} - {$user['nombre']} (HTTP {$httpCode})";
    }

    // Pequeña pausa para evitar rate limiting
    usleep(200000); // 200ms
}

echo json_encode([
    'success' => true,
    'total' => $total,
    'sent' => $sent,
    'failed' => $failed,
    'failed_names' => $failed_names,
    'log' => $log,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
