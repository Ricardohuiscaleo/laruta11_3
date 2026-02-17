<?php
// Helper para obtener token válido (con auto-refresh)
function getValidGmailToken() {
    $config = require __DIR__ . '/../../config.php';
    $token_file = __DIR__ . '/../../gmail_token.json';
    
    if (!file_exists($token_file)) {
        return ['error' => 'Token no encontrado. Debes autenticarte primero en /api/gmail/auth.php'];
    }
    
    $token_data = json_decode(file_get_contents($token_file), true);
    
    // Si el token no ha expirado, devolverlo
    if (time() < $token_data['expires_at']) {
        return ['access_token' => $token_data['access_token']];
    }
    
    // Token expirado, renovar con refresh_token
    $token_url = 'https://oauth2.googleapis.com/token';
    $data = [
        'client_id' => $config['gmail_client_id'],
        'client_secret' => $config['gmail_client_secret'],
        'refresh_token' => $token_data['refresh_token'],
        'grant_type' => 'refresh_token'
    ];
    
    $ch = curl_init($token_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);
    
    $new_tokens = json_decode($response, true);
    
    if (isset($new_tokens['error'])) {
        return ['error' => 'Error al renovar token: ' . $new_tokens['error_description']];
    }
    
    // Actualizar token guardado
    $token_data['access_token'] = $new_tokens['access_token'];
    $token_data['expires_at'] = time() + $new_tokens['expires_in'];
    $token_data['refreshed_at'] = date('Y-m-d H:i:s');
    
    file_put_contents($token_file, json_encode($token_data, JSON_PRETTY_PRINT));
    
    return ['access_token' => $new_tokens['access_token']];
}
