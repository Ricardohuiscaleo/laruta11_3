<?php
function buildDynamicEmailHtml($user, $credito_total, $credito_usado, $credito_disponible, $mes, $anio, $dias_restantes, $dias_mora, $tipo) {
    $nombre = htmlspecialchars($user['nombre']);
    $grado  = htmlspecialchars($user['grado_militar']);
    $unidad = htmlspecialchars($user['unidad_trabajo']);
    $fmt = fn($n) => '$' . number_format($n, 0, ',', '.');

    if ($tipo === 'sin_deuda') {
        $header_bg = 'linear-gradient(135deg,#10b981 0%,#059669 100%)';
        $titulo    = '✅ Sin Deuda Pendiente';
        $subtitulo = 'Tu crédito está al día';
        $badge     = "<div style='background:#d1fae5;color:#065f46;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;text-align:center;margin-bottom:20px;'>🎉 No tienes deuda pendiente este mes</div>";
        $cta_color = '#10b981'; $cta_text = '📊 Ver Estado de Cuenta';
    } elseif ($tipo === 'recordatorio') {
        $header_bg = 'linear-gradient(135deg,#f59e0b 0%,#d97706 100%)';
        $titulo    = '📅 Recordatorio de Pago';
        $subtitulo = $dias_restantes === 0 ? '¡Hoy vence tu pago!' : "Vence en $dias_restantes día" . ($dias_restantes === 1 ? '' : 's');
        $urg_bg    = $dias_restantes <= 3 ? '#fef2f2;border:2px solid #fca5a5' : '#fffbeb;border:2px solid #fcd34d';
        $urg_color = $dias_restantes <= 3 ? '#dc2626' : '#92400e';
        $badge     = "<div style='background:$urg_bg;color:$urg_color;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;text-align:center;margin-bottom:20px;'>⏰ Fecha límite: 21 de $mes, $anio</div>";
        $cta_color = '#3b82f6'; $cta_text = '💳 Pagar Ahora';
    } else {
        $header_bg = 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)';
        $titulo    = '⚠️ Pago Vencido';
        $subtitulo = "$dias_mora día" . ($dias_mora === 1 ? '' : 's') . " en mora";
        $badge     = "<div style='background:#fef2f2;border:2px solid #fca5a5;color:#dc2626;padding:12px 20px;border-radius:12px;font-size:14px;font-weight:700;text-align:center;margin-bottom:20px;'>🚨 $dias_mora día" . ($dias_mora === 1 ? '' : 's') . " de mora — Pago venció el 21 de $mes, $anio</div>";
        $cta_color = '#dc2626'; $cta_text = '💳 Pagar Ahora';
    }

    $resumen_bg  = $tipo === 'moroso' ? '#fef2f2' : ($tipo === 'recordatorio' ? '#fffbeb' : '#f0fdf4');
    $saldo_color = $tipo === 'moroso' ? '#dc2626' : ($tipo === 'recordatorio' ? '#d97706' : '#10b981');
    $uid         = $user['id'];
    $cta_html    = $tipo !== 'sin_deuda'
        ? "<div style='text-align:center;margin-bottom:20px;'><a href='https://app.laruta11.cl/pagar-credito?user_id=$uid' style='display:inline-block;background:$cta_color;color:#fff;text-decoration:none;padding:14px 40px;border-radius:10px;font-weight:700;font-size:16px;'>$cta_text</a><p style='color:#9ca3af;font-size:11px;margin:8px 0 0;'>Pago seguro procesado por TUU.cl</p></div>"
        : '';

    $anio_actual = date('Y');
    return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f4f4f4;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f4f4f4;padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.1);'>
  <tr><td style='background:$header_bg;padding:40px 20px;text-align:center;'>
    <h1 style='color:#fff;margin:0;font-size:28px;font-weight:800;'>$titulo</h1>
    <p style='color:rgba(255,255,255,0.9);margin:6px 0 0;font-size:14px;'>$subtitulo</p>
  </td></tr>
  <tr><td style='padding:30px 20px;'>
    <h2 style='color:#111827;margin:0 0 6px;font-size:20px;'>Hola, $nombre 👋</h2>
    <p style='color:#6b7280;font-size:13px;margin:0 0 20px;'>$grado — $unidad</p>
    $badge
    <div style='background:$resumen_bg;border-radius:12px;padding:20px;margin-bottom:20px;'>
      <h3 style='color:#374151;margin:0 0 16px;font-size:16px;font-weight:700;'>📊 Resumen de Cuenta</h3>
      <table width='100%' cellpadding='8' cellspacing='0'>
        <tr><td style='color:#6b7280;font-size:14px;'>Crédito Total:</td><td align='right' style='color:#111827;font-weight:700;'>" . $fmt($credito_total) . "</td></tr>
        <tr><td style='color:#6b7280;font-size:14px;'>Consumido:</td><td align='right' style='color:#111827;font-weight:700;'>" . $fmt($credito_usado) . "</td></tr>
        <tr><td style='color:#6b7280;font-size:14px;'>Disponible:</td><td align='right' style='color:#10b981;font-weight:700;'>" . $fmt($credito_disponible) . "</td></tr>
        <tr style='border-top:2px solid #e5e7eb;'><td style='color:#374151;font-size:16px;font-weight:700;padding-top:12px;'>Saldo a Pagar:</td><td align='right' style='color:$saldo_color;font-weight:800;font-size:22px;padding-top:12px;'>" . $fmt($credito_usado) . "</td></tr>
      </table>
    </div>
    $cta_html
  </td></tr>
  <tr><td style='background:#111827;padding:24px 20px;text-align:center;'>
    <p style='color:#6b7280;margin:0;font-size:11px;line-height:1.8;'>Yumbel 2629, Arica, Chile<br>📞 +56 9 3622 7422 | 📧 saboresdelaruta11@gmail.com<br><span style='color:#4b5563;'>© $anio_actual La Ruta 11 SpA.</span></p>
  </td></tr>
</table>
</td></tr>
</table>
</body></html>";
}

// Solo ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: text/html; charset=UTF-8');
    $config = require __DIR__ . '/../../config.php';
    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) die('user_id requerido');

    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    $stmt = $conn->prepare("SELECT id, nombre, email, limite_credito, credito_usado, grado_militar, unidad_trabajo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $conn->close();
    if (!$user) die('Usuario no encontrado');

    $credito_total     = floatval($user['limite_credito']);
    $credito_usado     = floatval($user['credito_usado']);
    $credito_disponible = $credito_total - $credito_usado;
    $day  = intval(date('j'));
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $mes  = $meses[date('n') - 1];
    $anio = date('Y');

    if ($credito_usado <= 0)   { $tipo = 'sin_deuda';    $dias_restantes = 0;        $dias_mora = 0; }
    elseif ($day <= 21)        { $tipo = 'recordatorio'; $dias_restantes = 21 - $day; $dias_mora = 0; }
    else                       { $tipo = 'moroso';       $dias_restantes = 0;        $dias_mora = $day - 21; }

    echo buildDynamicEmailHtml($user, $credito_total, $credito_usado, $credito_disponible, $mes, $anio, $dias_restantes, $dias_mora, $tipo);
}
?>
