<?php
/**
 * Sistema de colores por tipo:
 * - sin_deuda:    verde   #10b981 / #059669
 * - recordatorio: naranja #FF6B35 / #F7931E  (diseño base oficial)
 * - urgente:      rojo-naranja #ef4444 / #dc2626  (días 18-21)
 * - moroso:       rojo oscuro  #dc2626 / #991b1b
 */
function buildDynamicEmailHtml($user, $credito_total, $credito_usado, $credito_disponible, $mes, $anio, $dias_restantes, $dias_mora, $tipo) {
    $nombre = htmlspecialchars($user['nombre']);
    $grado  = htmlspecialchars($user['grado_militar']);
    $unidad = htmlspecialchars($user['unidad_trabajo']);
    $uid    = $user['id'];
    $fmt    = fn($n) => number_format($n, 0, ',', '.');

    // Colores por tipo
    $themes = [
        'sin_deuda'    => ['grad' => 'linear-gradient(135deg,#10b981 0%,#059669 100%)', 'bg' => '#f0fdf4',  'border' => '#bbf7d0', 'badge_bg' => '#d1fae5', 'badge_color' => '#065f46', 'fecha_bg' => '#d1fae5', 'fecha_color' => '#065f46', 'fecha_border' => '#6ee7b7', 'icon_bg' => '#10b981'],
        'recordatorio' => ['grad' => 'linear-gradient(135deg,#FF6B35 0%,#F7931E 100%)',  'bg' => '#fff7ed',  'border' => '#fed7aa', 'badge_bg' => '#fff7ed', 'badge_color' => '#c2410c', 'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d', 'fecha_border' => '#fecaca', 'icon_bg' => '#ef4444'],
        'urgente'      => ['grad' => 'linear-gradient(135deg,#ef4444 0%,#dc2626 100%)',  'bg' => '#fef2f2',  'border' => '#fecaca', 'badge_bg' => '#fef2f2', 'badge_color' => '#991b1b', 'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d', 'fecha_border' => '#fca5a5', 'icon_bg' => '#dc2626'],
        'moroso'       => ['grad' => 'linear-gradient(135deg,#dc2626 0%,#991b1b 100%)',  'bg' => '#fef2f2',  'border' => '#fca5a5', 'badge_bg' => '#fef2f2', 'badge_color' => '#7f1d1d', 'fecha_bg' => '#fef2f2', 'fecha_color' => '#7f1d1d', 'fecha_border' => '#fca5a5', 'icon_bg' => '#991b1b'],
    ];
    $t = $themes[$tipo] ?? $themes['recordatorio'];

    // Badge de alerta dinámico
    if ($tipo === 'sin_deuda') {
        $alert_badge = "<tr><td style='padding:0 20px 24px;'><div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'><p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>🎉 ¡Tu crédito está al día este mes!</p></div></td></tr>";
    } elseif ($tipo === 'recordatorio') {
        $alert_badge = "<tr><td style='padding:0 20px 24px;'><div style='background:{$t['badge_bg']};border:2px dashed {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'><p style='color:{$t['badge_color']};font-size:14px;font-weight:800;margin:0;'>📅 Tienes <strong>$dias_restantes día" . ($dias_restantes === 1 ? '' : 's') . "</strong> para pagar antes del 21 de $mes</p></div></td></tr>";
    } elseif ($tipo === 'urgente') {
        $alert_badge = "<tr><td style='padding:0 20px 24px;'><div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'><p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>🚨 ¡ÚLTIMO AVISO! Vence " . ($dias_restantes === 0 ? 'HOY' : "en $dias_restantes día" . ($dias_restantes === 1 ? '' : 's')) . " — 21 de $mes, $anio</p></div></td></tr>";
    } else {
        $alert_badge = "<tr><td style='padding:0 20px 24px;'><div style='background:{$t['badge_bg']};border:2px solid {$t['border']};border-radius:16px;padding:16px 20px;text-align:center;'><p style='color:{$t['badge_color']};font-size:15px;font-weight:800;margin:0;'>⚠️ Tu pago venció hace <strong>$dias_mora día" . ($dias_mora === 1 ? '' : 's') . "</strong> — Regulariza tu situación</p></div></td></tr>";
    }

    $fecha_vencimiento = "21 de $mes, $anio";
    $anio_actual = date('Y');
    $cta_btn = $tipo !== 'sin_deuda'
        ? "<tr><td style='padding:0 20px 35px;' align='center'><a href='https://app.laruta11.cl/pagar-credito?user_id=$uid&monto={$fmt($credito_usado)}' style='display:inline-block;background:linear-gradient(135deg,#3b82f6 0%,#2563eb 100%);color:#ffffff;text-decoration:none;padding:20px 40px;border-radius:32px;font-weight:800;font-size:18px;box-shadow:0 10px 30px rgba(59,130,246,0.3);white-space:nowrap;'>&#128179; PAGAR MI CR&Eacute;DITO</a><p style='color:#9ca3af;font-size:11px;margin:24px 0 0;font-weight:700;'><span style='display:inline-block;width:8px;height:8px;background:#10b981;border-radius:50%;margin-right:6px;'></span>PROCESO 100% SEGURO V&Iacute;A TUU.CL</p></td></tr>"
        : '';

    return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1.0'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,&quot;Segoe UI&quot;,Roboto,sans-serif;background-color:{$t['bg']};'>
<table width='100%' cellpadding='0' cellspacing='0' style='background-color:{$t['bg']};padding:10px;'>
<tr><td align='center'>
<table width='600' cellpadding='0' cellspacing='0' style='background-color:#ffffff;border-radius:40px;overflow:hidden;box-shadow:0 10px 40px -10px rgba(0,0,0,0.15);border:1px solid {$t['border']};'>

  <tr><td style='background:{$t['grad']};padding:48px 20px;text-align:center;'>
    <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width:80px;height:80px;margin:0 auto 16px;display:block;filter:drop-shadow(0 10px 20px rgba(0,0,0,0.2));'>
    <h1 style='color:#ffffff;margin:0;font-size:36px;font-weight:800;letter-spacing:-0.5px;'>La Ruta 11</h1>
    <p style='color:rgba(255,255,255,0.85);margin:4px 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:4px;'>Estado de Cuenta</p>
  </td></tr>

  <tr><td style='padding:32px 20px 20px;background:#ffffff;'>
    <div style='text-align:center;margin-bottom:32px;'>
      <h2 style='color:#111827;margin:0 0 12px;font-size:24px;font-weight:800;'>¡Hola, $nombre! 👋</h2>
      <p style='color:#6b7280;line-height:1.6;margin:0;font-size:14px;font-weight:500;'>Aquí tienes el detalle de tu crédito <strong>RL6</strong>. ¡Gracias por tu confianza!</p>
    </div>
    <table width='100%' cellpadding='0' cellspacing='0'>
      <tr><td align='center' style='padding-bottom:32px;'>
        <div style='display:inline-block;background:{$t['badge_bg']};padding:8px 24px;border-radius:999px;margin:0 8px;'>
          <p style='color:{$t['badge_color']};margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>$grado</p>
        </div>
        <div style='display:inline-block;background:#f3f4f6;padding:8px 24px;border-radius:999px;margin:0 8px;'>
          <p style='color:#4b5563;margin:0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;'>Unidad $unidad</p>
        </div>
      </td></tr>
    </table>
  </td></tr>

  $alert_badge

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:#f9fafb;padding:24px;border-radius:32px;'>
      <h3 style='text-align:center;font-size:10px;font-weight:900;color:#9ca3af;text-transform:uppercase;letter-spacing:3px;margin:0 0 24px;'>Resumen Financiero</h3>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#9ca3af;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Cupo Total</p><p style='color:#1f2937;font-size:20px;font-weight:800;margin:0;'>\${$fmt($credito_total)}</p></div></td>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#fb923c;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Consumido</p><p style='color:#ea580c;font-size:20px;font-weight:800;margin:0;'>\${$fmt($credito_usado)}</p></div></td>
        </tr>
        <tr>
          <td width='50%' style='padding:8px;'><div style='background:#ffffff;border:2px solid #f8fafc;padding:20px;border-radius:24px;text-align:center;'><p style='color:#34d399;font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Disponible</p><p style='color:#059669;font-size:20px;font-weight:800;margin:0;'>\${$fmt($credito_disponible)}</p></div></td>
          <td width='50%' style='padding:8px;'><div style='background:{$t['grad']};padding:20px;border-radius:24px;text-align:center;box-shadow:0 4px 14px rgba(0,0,0,0.15);'><p style='color:rgba(255,255,255,0.85);font-size:9px;font-weight:700;text-transform:uppercase;margin:0 0 4px;'>Total a Pagar</p><p style='color:#ffffff;font-size:20px;font-weight:800;margin:0;'>\${$fmt($credito_usado)}</p></div></td>
        </tr>
      </table>
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:{$t['fecha_bg']};border-radius:24px;padding:20px;border:2px dashed {$t['fecha_border']};'>
      <table width='100%' cellpadding='0' cellspacing='0'><tr>
        <td width='48' style='padding-right:16px;'><div style='background:{$t['icon_bg']};color:#ffffff;width:48px;height:48px;border-radius:16px;text-align:center;line-height:48px;font-size:20px;box-shadow:0 4px 14px rgba(0,0,0,0.2);'>🗓️</div></td>
        <td><p style='color:{$t['fecha_color']};font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 0 4px;'>Fecha Límite</p><p style='color:{$t['fecha_color']};font-size:18px;font-weight:700;margin:0;'>$fecha_vencimiento</p></td>
      </tr></table>
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 32px;'>
    <div style='background:{$t['bg']};border:2px solid {$t['border']};border-radius:24px;padding:24px;'>
      <h3 style='color:{$t['badge_color']};margin:0 0 20px;font-size:16px;font-weight:800;text-align:center;'>💡 Cómo Pagar tu Crédito</h3>
      " . implode('', array_map(fn($n, $txt) => "
      <table width='100%' cellpadding='0' cellspacing='0' style='margin-bottom:10px;'><tr>
        <td width='32' style='padding-right:12px;vertical-align:top;'><div style='background:{$t['grad']};color:white;width:32px;height:32px;border-radius:50%;text-align:center;line-height:32px;font-weight:800;font-size:14px;'>$n</div></td>
        <td style='vertical-align:top;'><p style='color:{$t['fecha_color']};font-size:14px;line-height:32px;margin:0;font-weight:600;'>$txt</p></td>
      </tr></table>", [1,2,3], ['Inicia sesión en <strong>app.laruta11.cl</strong>', 'Ve a tu <strong>Perfil</strong> → <strong>Crédito</strong>', 'Haz clic en <strong>&quot;Pagar Crédito&quot;</strong>'])) . "
    </div>
  </td></tr>

  <tr><td style='padding:0 20px 8px;text-align:center;'><p style='color:#9ca3af;font-size:11px;margin:0;font-weight:500;'>✨ Si ya iniciaste sesi&oacute;n haz clic ac&aacute; abajo 👇</p></td></tr>

  $cta_btn

  <tr><td style='background-color:#111827;padding:40px 20px;text-align:center;'>
    <table width='100%' cellpadding='0' cellspacing='0'><tr><td align='center' style='padding-bottom:32px;'>
      <a href='tel:+56936227422' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>Soporte</a>
      <a href='tel:+56945392581' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>Ventas</a>
      <a href='https://app.laruta11.cl' style='color:#ffffff;text-decoration:none;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:2px;margin:0 16px;'>App</a>
    </td></tr></table>
    <p style='color:#6b7280;margin:0;font-size:11px;line-height:1.8;font-weight:500;'>Yumbel 2629, Arica, Chile<br><span style='color:#4b5563;'>© $anio_actual La Ruta 11 SpA. Sabores con historia.</span></p>
  </td></tr>

</table>
</td></tr>
</table>
</body></html>";
}

// Solo ejecutar si se llama directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    header('Content-Type: text/html; charset=UTF-8');
    $config  = require __DIR__ . '/../../config.php';
    $user_id = intval($_GET['user_id'] ?? 0);
    if (!$user_id) die('user_id requerido');

    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    $stmt = $conn->prepare("SELECT id, nombre, email, limite_credito, credito_usado, grado_militar, unidad_trabajo, fecha_ultimo_pago FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $conn->close();
    if (!$user) die('Usuario no encontrado');

    $credito_total      = floatval($user['limite_credito']);
    $credito_usado      = floatval($user['credito_usado']);
    $credito_disponible = $credito_total - $credito_usado;
    $day   = intval(date('j'));
    $pago_este_mes = !empty($user['fecha_ultimo_pago']) && substr($user['fecha_ultimo_pago'], 0, 7) === date('Y-m');
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $mes_idx = (int)date('n') - 1;
    $anio  = (int)date('Y');
    // Si pagó este mes, el vencimiento es el 21 del mes siguiente
    if ($pago_este_mes) {
        $mes_idx = ($mes_idx + 1) % 12;
        if ($mes_idx === 0) $anio++;
    }
    $mes = $meses[$mes_idx];
    if ($credito_usado <= 0)        { $tipo = 'sin_deuda';    $dias_restantes = 0;         $dias_mora = 0; }
    elseif ($pago_este_mes)         { $tipo = 'recordatorio'; $dias_restantes = 21;         $dias_mora = 0; }
    elseif ($day <= 20)             { $tipo = 'recordatorio'; $dias_restantes = 21 - $day; $dias_mora = 0; }
    elseif ($day === 21)            { $tipo = 'urgente';      $dias_restantes = 0;          $dias_mora = 0; }
    else                            { $tipo = 'moroso';       $dias_restantes = 0;         $dias_mora = $day - 21; }

    echo buildDynamicEmailHtml($user, $credito_total, $credito_usado, $credito_disponible, $mes, $anio, $dias_restantes, $dias_mora, $tipo);
}
?>
