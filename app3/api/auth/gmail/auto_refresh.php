<?php
// Sistema de auto-renovación de tokens Gmail
function checkAndRefreshToken() {
    $tokenPath = __DIR__ . '/gmail_token.json';
    
    if (!file_exists($tokenPath)) {
        return false;
    }
    
    $tokenData = json_decode(file_get_contents($tokenPath), true);
    
    if (!isset($tokenData['created']) || !isset($tokenData['expires_in'])) {
        return false;
    }
    
    $tokenAge = time() - $tokenData['created'];
    $expiresIn = $tokenData['expires_in'];
    
    // Renovar si queda menos de 5 minutos (300 segundos)
    if ($tokenAge >= ($expiresIn - 300)) {
        require_once __DIR__ . '/refresh_token.php';
        return refreshGmailToken();
    }
    
    return true; // Token aún válido
}

// Función para incluir en otros archivos
function ensureValidGmailToken() {
    return checkAndRefreshToken();
}
?>