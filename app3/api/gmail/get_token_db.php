<?php
// Helper para obtener token válido desde BASE DE DATOS (con auto-refresh)
function getValidGmailToken() {
    $config = require __DIR__ . '/../../config.php';
    
    // Conectar a BD
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    
    if ($conn->connect_error) {
        return ['error' => 'Error de conexión a BD'];
    }
    
    // Obtener token de BD
    $result = $conn->query("SELECT * FROM gmail_tokens WHERE id = 1");
    
    if ($result->num_rows === 0) {
        $conn->close();
        return ['error' => 'Token no encontrado. Debes autenticarte primero en /api/gmail/auth.php'];
    }
    
    $token_data = $result->fetch_assoc();
    $conn->close();
    
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
    
    // Actualizar token en BD
    $conn = new mysqli($config['app_db_host'], $config['app_db_user'], $config['app_db_pass'], $config['app_db_name']);
    $new_expires_at = time() + $new_tokens['expires_in'];
    
    $stmt = $conn->prepare("UPDATE gmail_tokens SET access_token=?, expires_at=?, updated_at=NOW() WHERE id=1");
    $stmt->bind_param('si', $new_tokens['access_token'], $new_expires_at);
    $stmt->execute();
    $stmt->close();
    $conn->close();
    
    return ['access_token' => $new_tokens['access_token']];
}

function get_gmail_token_from_db($config) {
    $result = getValidGmailToken();
    return isset($result['access_token']) ? $result['access_token'] : null;
}
