<?php
$config = null;
foreach ([__DIR__ . '/../../public/config.php', __DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../../config.php', __DIR__ . '/../../../../config.php'] as $p) {
  if (file_exists($p)) {
    $config = require_once $p;
    break;
  }
}
if (!$config) {
  echo '<p>Config no encontrado</p>';
  exit;
}

$personal_id = intval($_GET['personal_id'] ?? 0);
$mes = $_GET['mes'] ?? date('Y-m'); // formato YYYY-MM

if (!$personal_id) {
  echo '<p>personal_id requerido</p>';
  exit;
}

$conn = mysqli_connect($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
if (!$conn) {
  echo '<p>Error BD</p>';
  exit;
}

// Datos del colaborador
$stmt = mysqli_prepare($conn, "SELECT * FROM personal WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $personal_id);
mysqli_stmt_execute($stmt);
$persona = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$persona) {
  echo '<p>Colaborador no encontrado</p>';
  exit;
}

$mesDate = $mes . '-01';

// Ajustes del mes
$stmt2 = mysqli_prepare($conn, "SELECT * FROM ajustes_sueldo WHERE personal_id = ? AND mes = ?");
mysqli_stmt_bind_param($stmt2, 'is', $personal_id, $mesDate);
mysqli_stmt_execute($stmt2);
$ajustes = mysqli_stmt_get_result($stmt2);
$ajustesList = [];
while ($row = mysqli_fetch_assoc($ajustes))
  $ajustesList[] = $row;

// Pago real registrado (si existe)
$stmt3 = mysqli_prepare($conn, "SELECT * FROM pagos_nomina WHERE personal_id = ? AND mes = ? LIMIT 1");
mysqli_stmt_bind_param($stmt3, 'is', $personal_id, $mesDate);
mysqli_stmt_execute($stmt3);
$pagoReal = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt3));

$sueldoBase = floatval($persona['sueldo_base_cajero'] ?? 0) + floatval($persona['sueldo_base_planchero'] ?? 0) + floatval($persona['sueldo_base_admin'] ?? 0) + floatval($persona['sueldo_base_seguridad'] ?? 0);
$totalAjustes = array_reduce($ajustesList, fn($s, $a) => $s + floatval($a['monto']), 0);
$total = $sueldoBase + $totalAjustes;
$montoFinal = $pagoReal ? floatval($pagoReal['monto']) : $total;

$mesesNombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesNum = intval(substr($mes, 5, 2));
$anio = substr($mes, 0, 4);
$mesLabel = ucfirst($mesesNombres[$mesNum - 1]) . ' ' . $anio;

$rolLabel = ucfirst($persona['rol']);
$nombre = htmlspecialchars($persona['nombre']);

// Filas de ajustes
$ajustesHtml = '';
foreach ($ajustesList as $a) {
  $m = floatval($a['monto']);
  $color = $m < 0 ? '#ef4444' : '#10b981';
  $signo = $m < 0 ? '-' : '+';
  $ajustesHtml .= "
    <tr>
        <td style='padding:8px 0;border-bottom:1px solid #f1f5f9;color:#64748b;font-size:13px;'>" . htmlspecialchars($a['concepto']) . "</td>
        <td style='padding:8px 0;border-bottom:1px solid #f1f5f9;text-align:right;font-weight:600;color:{$color};font-size:13px;'>{$signo}\$" . number_format(abs($m), 0, ',', '.') . "</td>
    </tr>";
}

echo "<!DOCTYPE html>
<html lang='es'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8fafc;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f8fafc;padding:20px 10px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);border:1px solid #e2e8f0;'>

  <!-- Header -->
  <tr>
    <td style='background:linear-gradient(135deg,#1e293b 0%,#334155 100%);padding:32px 24px;text-align:center;'>
      <img src='https://laruta11-images.s3.amazonaws.com/menu/logo.png' alt='La Ruta 11' style='width:56px;height:56px;margin:0 auto 12px;display:block;'>
      <h1 style='color:#ffffff;margin:0;font-size:22px;font-weight:800;'>La Ruta 11</h1>
      <p style='color:#94a3b8;margin:4px 0 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:3px;'>Liquidación de Sueldo</p>
    </td>
  </tr>

  <!-- Saludo -->
  <tr>
    <td style='padding:28px 24px 16px;'>
      <h2 style='color:#1e293b;margin:0 0 6px;font-size:20px;font-weight:800;'>¡Hola, {$nombre}! 👋</h2>
      <p style='color:#64748b;margin:0;font-size:13px;'>Aquí está el detalle de tu liquidación de <strong>{$mesLabel}</strong>.</p>
      <div style='display:inline-block;margin-top:10px;background:#f1f5f9;padding:4px 14px;border-radius:20px;'>
        <span style='font-size:12px;font-weight:600;color:#475569;'>{$rolLabel}</span>
      </div>
    </td>
  </tr>

  <!-- Detalle -->
  <tr>
    <td style='padding:0 24px 24px;'>
      <div style='background:#f8fafc;border-radius:12px;padding:20px;'>
        <table width='100%' cellpadding='0' cellspacing='0'>
          <tr>
            <td style='padding:8px 0;border-bottom:1px solid #e2e8f0;font-weight:700;color:#1e293b;font-size:14px;'>Sueldo base</td>
            <td style='padding:8px 0;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:700;color:#1e293b;font-size:14px;'>\$" . number_format($sueldoBase, 0, ',', '.') . "</td>
          </tr>
          {$ajustesHtml}
          <tr>
            <td style='padding:14px 0 0;font-weight:800;color:#1e293b;font-size:16px;'>Total a pagar</td>
            <td style='padding:14px 0 0;text-align:right;font-weight:800;font-size:20px;color:#10b981;'>\$" . number_format($montoFinal, 0, ',', '.') . "</td>
          </tr>
        </table>
      </div>
    </td>
  </tr>

  <!-- Nota -->
  <tr>
    <td style='padding:0 24px 28px;'>
      <div style='background:#eff6ff;border-radius:10px;padding:14px 18px;border-left:4px solid #3b82f6;'>
        <p style='margin:0;font-size:13px;color:#1d4ed8;font-weight:600;'>💙 Gracias por tu trabajo y dedicación en La Ruta 11.</p>
      </div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style='background:#1e293b;padding:20px 24px;text-align:center;'>
      <p style='color:#64748b;margin:0;font-size:11px;'>Yumbel 2629, Arica, Chile · saboresdelaruta11@gmail.com</p>
      <p style='color:#475569;margin:6px 0 0;font-size:11px;'>© " . date('Y') . " La Ruta 11 SpA</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body></html>";

mysqli_close($conn);
?>