<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../gmail/get_token_db.php';
require_once __DIR__ . '/email_template.php';

$config = null;
foreach ([__DIR__ . '/../../public/config.php', __DIR__ . '/../config.php', __DIR__ . '/../../config.php', __DIR__ . '/../../../config.php', __DIR__ . '/../../../../config.php'] as $p) {
  if (file_exists($p)) {
    $config = require_once $p;
    break;
  }
}
if (!$config) {
  echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
  exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data || empty($data['email'])) {
  echo json_encode(['success' => false, 'error' => 'Falta payload o email']);
  exit;
}

$html = buildPayrollEmailHtml($data);

// Obtener token Gmail
$token_result = getValidGmailToken();
if (isset($token_result['error'])) {
  echo json_encode(['success' => false, 'error' => $token_result['error']]);
  exit;
}
$access_token = $token_result['access_token'];

$from = $config['gmail_sender_email'];
$to = $data['email'];

$mesesNombres = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$mesNum = intval(substr($data['mes'] ?? date('Y-m'), 5, 2));
$anio = substr($data['mes'] ?? date('Y-m'), 0, 4);
$mesStr = ucfirst($mesesNombres[$mesNum - 1] ?? '') . ' ' . $anio;

$subject = "💰 Notificación de Pago {$mesStr} — La Ruta 11";

$message = "From: La Ruta 11 <{$from}>\r\n";
$message .= "To: {$to}\r\n";
$message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: base64\r\n\r\n";
$message .= chunk_split(base64_encode($html));

$encoded = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');

$ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode(['raw' => $encoded]),
  CURLOPT_HTTPHEADER => [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json'
  ]
]);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$result = json_decode($response, true);

if ($http_code === 200) {
  echo json_encode([
    'success' => true,
    'email' => $to,
    'nombre' => $data['nombre'] ?? 'Desconocido',
    'monto' => $data['granTotal'] ?? 0,
    'message_id' => $result['id'] ?? null
  ]);
}
else {
  echo json_encode([
    'success' => false,
    'error' => 'Error Gmail API (HTTP ' . $http_code . ')',
    'details' => $result
  ]);
}
?>