<?php
require_once __DIR__ . '/../gmail/get_token_db.php';

function sendRL6Email($to, $nombre, $rut, $grado, $unidad, $tipo = 'registro', $extra = null) {
    $token_result = getValidGmailToken();
    if (isset($token_result['error'])) {
        error_log('sendRL6Email: token error - ' . $token_result['error']);
        return false;
    }
    $token = $token_result['access_token'];

    if ($tipo === 'registro') {
        $subject = '✅ Solicitud RL6 Recibida - La Ruta 11';
        $color   = '#f59e0b';
        $title   = '🎖️ Solicitud RL6 Recibida';
        $intro   = 'Hemos recibido tu solicitud de crédito RL6. Nuestro equipo está revisando tu información.';
        $body    = "
        <div style='background:#f3f4f6;padding:15px;border-radius:8px;margin:20px 0;'>
            <p><strong>RUT:</strong> $rut</p>
            <p><strong>Grado:</strong> $grado</p>
            <p><strong>Unidad:</strong> $unidad</p>
            <p><strong>Estado:</strong> <span style='color:#f59e0b;font-weight:bold;'>EN REVISIÓN</span></p>
        </div>
        <ul>
            <li>Validaremos tu información en máximo 24 horas</li>
            <li>Te contactaremos por email</li>
            <li>Si es aprobado, podrás usar tu crédito de inmediato</li>
        </ul>";

    } elseif ($tipo === 'aprobado') {
        $limite  = $extra ?? 50000;
        $meses   = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
        $dias    = max(0, 21 - intval(date('j')));
        $mes     = $meses[date('n') - 1];
        $subject = '🎉 Crédito RL6 Aprobado - La Ruta 11';
        $color   = '#10b981';
        $title   = '🎉 ¡Crédito RL6 Aprobado!';
        $intro   = "Tu solicitud de crédito RL6 ha sido <strong style='color:#10b981;'>APROBADA</strong>.";
        $body    = "
        <div style='background:#d1fae5;padding:20px;border-radius:8px;margin:20px 0;text-align:center;'>
            <p style='font-size:28px;font-weight:bold;color:#10b981;margin:0;'>$" . number_format($limite, 0, ',', '.') . "</p>
            <p style='color:#065f46;margin:4px 0 0;'>Disponible de inmediato</p>
        </div>
        <ol>
            <li>Abre la app y ve a tu Perfil → Crédito</li>
            <li>En el checkout elige \"Pagar con Crédito RL6\"</li>
            <li>Paga el 21 de $mes, te quedan $dias días 😊</li>
        </ol>";

    } elseif ($tipo === 'rechazado') {
        $motivo  = $extra ?? 'No se pudo validar la información proporcionada';
        $subject = '❌ Solicitud RL6 No Aprobada - La Ruta 11';
        $color   = '#ef4444';
        $title   = 'Solicitud RL6 No Aprobada';
        $intro   = 'Lamentamos informarte que tu solicitud no pudo ser aprobada en esta ocasión.';
        $body    = "
        <div style='background:#fee2e2;padding:15px;border-radius:8px;margin:20px 0;'>
            <p><strong>Motivo:</strong> $motivo</p>
        </div>
        <ul>
            <li>Contáctanos para más información</li>
            <li>Verifica tus datos y vuelve a intentar</li>
        </ul>";
    } else {
        return false;
    }

    $html = "
<!DOCTYPE html><html lang='es'><head><meta charset='UTF-8'></head>
<body style='margin:0;padding:5px;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,sans-serif;background:#f9fafb;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f9fafb;padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:16px;overflow:hidden;border:2px solid $color;'>
    <tr><td style='background:$color;padding:32px 20px;text-align:center;'>
        <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width:60px;height:60px;display:block;margin:0 auto 12px;'>
        <h1 style='color:#fff;margin:0;font-size:24px;font-weight:800;'>$title</h1>
    </td></tr>
    <tr><td style='padding:24px 20px;'>
        <p>Hola <strong>" . htmlspecialchars($nombre) . "</strong>,</p>
        <p>$intro</p>
        $body
        <p style='margin-top:24px;padding-top:16px;border-top:1px solid #e5e7eb;text-align:center;color:#6b7280;font-size:13px;'>
            La Ruta 11 - Sistema RL6<br>
            <a href='https://wa.me/56936227422' style='color:$color;'>WhatsApp: +56 9 3622 7422</a>
        </p>
    </td></tr>
</table>
</td></tr></table>
</body></html>";

    $raw = rtrim(strtr(base64_encode(
        "From: La Ruta 11 <saboresdelaruta11@gmail.com>\r\n" .
        "To: $to\r\n" .
        "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n\r\n" .
        $html
    ), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $raw]));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log('sendRL6Email: Gmail API error ' . $httpCode . ' - ' . $response);
        return false;
    }
    return true;
}
