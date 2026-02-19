<?php
// Verificar token de Gmail
// Acceder: https://app.laruta11.cl/api/gmail/check_token.php

header('Content-Type: text/plain; charset=utf-8');

$config = require_once __DIR__ . '/../../config.php';

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("SELECT * FROM gmail_tokens ORDER BY updated_at DESC LIMIT 1");
    $token = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$token) {
        die("❌ No hay token en la base de datos\n");
    }
    
    echo "📧 Token de Gmail:\n\n";
    echo "ID: {$token['id']}\n";
    echo "Última actualización: {$token['updated_at']}\n";
    echo "Creado: {$token['created_at']}\n";
    echo "Token (primeros 50 chars): " . substr($token['access_token'], 0, 50) . "...\n\n";
    
    // Calcular tiempo desde última actualización
    $updated = new DateTime($token['updated_at']);
    $now = new DateTime();
    $diff = $now->diff($updated);
    
    $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;
    
    echo "⏰ Tiempo desde última actualización: {$minutes} minutos\n";
    
    if ($minutes > 60) {
        echo "⚠️ Token probablemente expirado (>60 min)\n";
        echo "💡 GitHub Actions debería renovarlo cada 30 minutos\n";
    } else if ($minutes > 30) {
        echo "⚠️ Token cerca de expirar (>30 min)\n";
    } else {
        echo "✅ Token debería estar válido (<30 min)\n";
    }
    
    // Probar token con Gmail API
    echo "\n🔄 Probando token con Gmail API...\n";
    
    $ch = curl_init('https://gmail.googleapis.com/gmail/v1/users/me/profile');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token['access_token']
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $http_code\n";
    
    if ($http_code === 200) {
        $profile = json_decode($response, true);
        echo "✅ Token válido!\n";
        echo "Email: {$profile['emailAddress']}\n";
    } else {
        echo "❌ Token inválido o expirado\n";
        echo "Response: $response\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
