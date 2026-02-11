<?php
// Función para renovar token automáticamente
function refreshGmailToken() {
    $config = require_once __DIR__ . '/../../../../../config.php';
    $tokenPath = __DIR__ . '/gmail_token.json';
    
    if (!file_exists($tokenPath)) {
        return false;
    }
    
    $tokenData = json_decode(file_get_contents($tokenPath), true);
    
    if (!isset($tokenData['refresh_token'])) {
        return false;
    }
    
    $postData = [
        'client_id' => $config['gmail_client_id'],
        'client_secret' => $config['gmail_client_secret'],
        'refresh_token' => $tokenData['refresh_token'],
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $newTokenData = json_decode($response, true);
    
    if (isset($newTokenData['access_token'])) {
        // Mantener refresh_token original
        $newTokenData['refresh_token'] = $tokenData['refresh_token'];
        $newTokenData['created'] = time();
        
        file_put_contents($tokenPath, json_encode($newTokenData, JSON_PRETTY_PRINT));
        return true;
    }
    
    return false;
}

// Auto-ejecutar si se llama directamente
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => refreshGmailToken()]);
}
?>