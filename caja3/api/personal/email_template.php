<?php
function buildPayrollEmailHtml($data)
{
    if (!$data)
        return '';

    $mesesNombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    $mes = $data['mes'] ?? date('Y-m');
    $mesNum = intval(substr($mes, 5, 2));
    $anio = substr($mes, 0, 4);
    $mesLabel = ucfirst($mesesNombres[$mesNum - 1] ?? '') . ' ' . $anio;

    $nombre = htmlspecialchars($data['nombre'] ?? 'Notificación');
    $rolLabel = htmlspecialchars(ucfirst($data['roles'] ?? ''));

    $sectionsHtml = '';
    $secciones = is_array($data['secciones'] ?? null) ? $data['secciones'] : [];

    foreach ($secciones as $sec) {
        $title = htmlspecialchars($sec['titulo'] ?? '');
        $sueldoBase = floatval($sec['sueldoBase'] ?? 0);
        $diasTrabajados = intval($sec['diasTrabajados'] ?? 0);
        $total = floatval($sec['total'] ?? 0);

        $baseLabel = (strpos(strtolower($title), 'liquidez') !== false) ? 'Liquidez Base' : "Sueldo Base ({$diasTrabajados} días)";

        $sectionsHtml .= "
        <tr><td colspan='2' style='padding:16px 0 8px;font-weight:800;color:#334155;font-size:14px;text-transform:uppercase;border-bottom:2px solid #e2e8f0;'>{$title}</td></tr>
        <tr>
            <td style='padding:10px 0;border-bottom:1px solid #f1f5f9;color:#475569;font-size:14px;font-weight:600;'>{$baseLabel}</td>
            <td style='padding:10px 0;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;color:#1e293b;font-size:14px;'>" . ($sueldoBase < 0 ? "-" : "") . "\$" . number_format(abs($sueldoBase), 0, ',', '.') . "</td>
        </tr>";

        $detalles = is_array($sec['detalles'] ?? null) ? $sec['detalles'] : [];
        foreach ($detalles as $det) {
            $t = htmlspecialchars($det['texto'] ?? '');
            $m = htmlspecialchars($det['monto'] ?? '');
            $c = htmlspecialchars($det['color'] ?? '#64748b');
            $sectionsHtml .= "
            <tr>
                <td style='padding:8px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:13px;'>{$t}</td>
                <td style='padding:8px 0;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:700;color:{$c};font-size:13px;'>{$m}</td>
            </tr>";
        }
        $sectionsHtml .= "
        <tr>
            <td style='padding:12px 0 24px;color:#64748b;font-size:12px;font-weight:800;text-align:right;text-transform:uppercase;'>Subtotal {$title}</td>
            <td style='padding:12px 0 24px;text-align:right;font-weight:800;color:#1e293b;font-size:16px;'>" . ($total < 0 ? "-" : "") . "\$" . number_format(abs($total), 0, ',', '.') . "</td>
        </tr>";
    }

    $montoFinal = floatval($data['granTotal'] ?? 0);

    return "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8fafc;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;padding:20px 5px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);border:1px solid #e2e8f0;'>
  <tr>
    <td style='background:linear-gradient(135deg,#1e293b 0%,#334155 100%);padding:32px 24px;text-align:center;'>
      <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width:56px;height:56px;margin:0 auto 12px;display:block;'>
      <h1 style='color:#ffffff;margin:0;font-size:22px;font-weight:800;'>La Ruta 11</h1>
      <p style='color:#94a3b8;margin:4px 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:3px;'>Notificación de Pago</p>
    </td>
  </tr>
  <tr>
    <td style='padding:28px 24px 16px;'>
      <h2 style='color:#1e293b;margin:0 0 6px;font-size:20px;font-weight:800;'>¡Hola, {$nombre}! 👋</h2>
      <p style='color:#64748b;margin:0;font-size:14px;line-height:1.5;'>Aquí tienes el desglose informativo de tu pago correspondiente a <strong>{$mesLabel}</strong>.</p>
      <div style='display:inline-block;margin-top:12px;background:#f1f5f9;padding:6px 16px;border-radius:20px;'>
        <span style='font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;'>{$rolLabel}</span>
      </div>
    </td>
  </tr>
  <tr>
    <td style='padding:0 24px 24px;'>
      <div style='background:#f8fafc;border-radius:16px;padding:24px;border:1px solid #e2e8f0;'>
        <table width='100%' cellpadding='0' cellspacing='0'>
          {$sectionsHtml}
          <tr>
            <td style='padding:20px 0 0;border-top:2px solid #cbd5e1;font-weight:900;color:#0f172a;font-size:18px;text-transform:uppercase;'>Total Transferido</td>
            <td style='padding:20px 0 0;border-top:2px solid #cbd5e1;text-align:right;font-weight:900;font-size:24px;color:#059669;'>\$" . number_format($montoFinal, 0, ',', '.') . "</td>
          </tr>
        </table>
      </div>
    </td>
  </tr>
  <tr>
    <td style='padding:0 24px 28px;'>
      <div style='background:#eff6ff;border-radius:12px;padding:16px 20px;border-left:4px solid #3b82f6;'>
        <p style='margin:0;font-size:14px;color:#1d4ed8;font-weight:600;'>💙 Gracias por tu enorme trabajo y dedicación al equipo de La Ruta 11.</p>
      </div>
    </td>
  </tr>
  <tr>
    <td style='background:#1e293b;padding:20px 24px;text-align:center;'>
      <p style='color:#94a3b8;margin:0;font-size:12px;'>Yumbel 2629, Arica, Chile · saboresdelaruta11@gmail.com</p>
      <p style='color:#475569;margin:8px 0 0;font-size:11px;'>© " . date('Y') . " La Ruta 11 SpA</p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body></html>";
}