<?php
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/get_token_db.php';
require_once __DIR__ . '/preview_email_dynamic.php';

$config  = require __DIR__ . '/../../config.php';
$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) { ob_end_clean(); echo json_encode(['success'=>false,'error'=>'user_id requerido']); exit; }

try {
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    $stmt = $conn->prepare("SELECT id, nombre, email, limite_credito, credito_usado, grado_militar, unidad_trabajo, fecha_ultimo_pago FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) { $conn->close(); throw new Exception('Usuario no encontrado'); }

    // Deuda del ciclo vencido: débitos entre día 22 mes anterior y día 21 mes actual
    $inicio_ciclo_vencido = date('Y-m-22', strtotime('first day of last month'));
    $fin_ciclo_vencido    = date('Y-m-21');
    $stmt2 = $conn->prepare("SELECT COALESCE(SUM(amount),0) FROM rl6_credit_transactions WHERE user_id=? AND type='debit' AND created_at BETWEEN ? AND ?");
    $fin_ciclo_vencido_ts = $fin_ciclo_vencido . ' 23:59:59';
    $stmt2->bind_param("iss", $user_id, $inicio_ciclo_vencido, $fin_ciclo_vencido_ts);
    $stmt2->execute();
    $deuda_ciclo_vencido = floatval($stmt2->get_result()->fetch_row()[0]);
    $conn->close();

    $credito_total      = floatval($user['limite_credito']);
    $credito_usado      = floatval($user['credito_usado']);
    $credito_disponible = $credito_total - $credito_usado;
    $day   = intval(date('j'));
    $pago_este_mes = !empty($user['fecha_ultimo_pago']) && substr($user['fecha_ultimo_pago'], 0, 7) === date('Y-m');
    $solo_deuda_ciclo_nuevo = ($credito_usado > 0 && $deuda_ciclo_vencido <= 0);
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $mes_idx = (int)date('n') - 1;
    $anio  = (int)date('Y');
    if ($pago_este_mes || $solo_deuda_ciclo_nuevo) {
        $mes_idx = ($mes_idx + 1) % 12;
        if ($mes_idx === 0) $anio++;
    }
    $mes = $meses[$mes_idx];

    if ($credito_usado <= 0) {
        $tipo = 'sin_deuda'; $dias_restantes = 0; $dias_mora = 0;
        $subject = "✅ Tu crédito está al día - La Ruta 11";
    } elseif ($pago_este_mes || $solo_deuda_ciclo_nuevo) {
        $tipo = 'recordatorio'; $dias_restantes = intval((strtotime(date('Y-m', strtotime('+1 month')) . '-21') - strtotime('today')) / 86400); $dias_mora = 0;
        $subject = "📅 Recordatorio de pago - Crédito RL6 La Ruta 11";
    } elseif ($day <= 20) {
        $tipo = 'recordatorio'; $dias_restantes = 21 - $day; $dias_mora = 0;
        $subject = "📅 Recordatorio de pago - Crédito RL6 La Ruta 11";
    } elseif ($day === 21) {
        $tipo = 'urgente'; $dias_restantes = 0; $dias_mora = 0;
        $subject = "🚨 ¡Último aviso! Tu pago vence HOY - La Ruta 11";
    } else {
        $tipo = 'moroso'; $dias_mora = $day - 21; $dias_restantes = 0;
        $subject = "⚠️ Pago vencido ($dias_mora días en mora) - La Ruta 11";
    }

    $html = buildDynamicEmailHtml($user, $credito_total, $credito_usado, $credito_disponible, $mes, $anio, $dias_restantes, $dias_mora, $tipo);

    $token_result = getValidGmailToken();
    if (isset($token_result['error'])) throw new Exception('Token Gmail: ' . $token_result['error']);
    $access_token = $token_result['access_token'];

    $from     = 'saboresdelaruta11@gmail.com';
    $message  = "From: La Ruta 11 <$from>\r\n";
    $message .= "To: {$user['email']}\r\n";
    $message .= "Cc: $from\r\n";
    $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $message .= chunk_split(base64_encode($html));

    $encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encoded]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.$access_token, 'Content-Type: application/json']);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($http_code !== 200) throw new Exception("Gmail error $http_code: $response");

    ob_end_clean();
    echo json_encode(['success'=>true, 'to'=>$user['email'], 'tipo'=>$tipo, 'subject'=>$subject]);

} catch (Exception $e) {
    ob_end_clean();
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]);
}
?>
