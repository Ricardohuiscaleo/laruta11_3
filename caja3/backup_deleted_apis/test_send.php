<?php
// Función simplificada para test de Gmail API
function testGmailSend($to, $subject, $htmlBody) {
    // Auto-renovar token si es necesario
    require_once __DIR__ . '/auto_refresh.php';
    if (!ensureValidGmailToken()) {
        return ['success' => false, 'error' => 'Token OAuth expirado y no se pudo renovar'];
    }
    
    // Cargar config
    $config = require_once __DIR__ . '/../../../../../config.php';
    
    // Verificar token
    $tokenFile = __DIR__ . '/gmail_token.json';
    if (!file_exists($tokenFile)) {
        return ['success' => false, 'error' => 'Token OAuth no encontrado'];
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        return ['success' => false, 'error' => 'Token OAuth inválido'];
    }
    
    // Preparar email
    $boundary = uniqid(rand(), true);
    $rawMessage = "To: {$to}\r\n";
    $rawMessage .= "From: La Ruta 11 <{$config['gmail_sender_email']}>\r\n";
    $rawMessage .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $rawMessage .= $htmlBody;
    
    $encodedMessage = base64_encode($rawMessage);
    $encodedMessage = str_replace(['+', '/', '='], ['-', '_', ''], $encodedMessage);
    
    // Enviar usando Gmail API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encodedMessage]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return ['success' => true];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
        return ['success' => false, 'error' => "Gmail API: {$errorMsg}"];
    }
}
?>