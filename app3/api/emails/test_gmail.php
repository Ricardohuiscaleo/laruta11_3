<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Verificar autenticación admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

try {
    // Verificar si existe el archivo de token Gmail
    $token_file = __DIR__ . '/../../gmail_token.json';
    
    if (!file_exists($token_file)) {
        throw new Exception('Token de Gmail no encontrado');
    }
    
    $token_data = json_decode(file_get_contents($token_file), true);
    
    if (!$token_data || !isset($token_data['access_token'])) {
        throw new Exception('Token de Gmail inválido');
    }
    
    // Verificar si el token está expirado
    $expires_at = $token_data['expires_at'] ?? 0;
    $current_time = time();
    
    if ($current_time >= $expires_at) {
        throw new Exception('Token de Gmail expirado');
    }
    
    // Probar conexión con Gmail API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://gmail.googleapis.com/gmail/v1/users/me/profile');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token_data['access_token'],
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Error de conexión con Gmail API (HTTP ' . $http_code . ')');
    }
    
    $profile = json_decode($response, true);
    
    if (!$profile || !isset($profile['emailAddress'])) {
        throw new Exception('Respuesta inválida de Gmail API');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Gmail API funcionando correctamente',
        'email' => $profile['emailAddress'],
        'token_expires' => date('Y-m-d H:i:s', $expires_at)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>