<?php
header('Content-Type: application/json');
require_once __DIR__ . '/get_token.php';

$config = require_once __DIR__ . '/../../config.php';

// Obtener token válido (auto-refresh si es necesario)
$token_result = getValidGmailToken();

if (isset($token_result['error'])) {
    echo json_encode(['success' => false, 'error' => $token_result['error']]);
    exit;
}

$access_token = $token_result['access_token'];

// Obtener datos del email (POST o GET para testing)
$to = $_POST['to'] ?? $_GET['to'] ?? 'test@example.com';
$subject = $_POST['subject'] ?? $_GET['subject'] ?? 'Test desde La Ruta 11';
$body = $_POST['body'] ?? $_GET['body'] ?? 'Este es un email de prueba desde La Ruta 11.';

// Crear mensaje en formato RFC 2822
$from = $config['gmail_sender_email'];
$message = "From: $from\r\n";
$message .= "To: $to\r\n";
$message .= "Subject: $subject\r\n";
$message .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
$message .= $body;

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
curl_close($ch);

$result = json_decode($response, true);

if ($http_code === 200) {
    echo json_encode([
        'success' => true,
        'message' => 'Email enviado correctamente',
        'message_id' => $result['id'],
        'to' => $to,
        'subject' => $subject
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Error al enviar email',
        'details' => $result
    ]);
}
