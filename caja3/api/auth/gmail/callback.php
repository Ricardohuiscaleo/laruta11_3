<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Cargar config desde raíz
    $config = require_once __DIR__ . '/../../../../../config.php';
    $client_id = $config['gmail_client_id'];
    $client_secret = $config['gmail_client_secret'];
    $redirect_uri = $config['gmail_redirect_uri'];
} catch (Exception $e) {
    die('Error cargando config: ' . $e->getMessage());
}

if (isset($_GET['code'])) {
    $code = $_GET['code'];
    
    // Intercambiar código por token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirect_uri
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "<h2>❌ Error CURL</h2>";
        echo "<p>Error: " . $curlError . "</p>";
        exit;
    }
    
    echo "<h3>Debug Info:</h3>";
    echo "<p>HTTP Code: " . $httpCode . "</p>";
    echo "<p>Response: " . htmlspecialchars($response) . "</p>";
    
    $tokenData = json_decode($response, true);
    
    if (isset($tokenData['access_token'])) {
        // Agregar timestamp para control de expiración
        $tokenData['created'] = time();
        
        // Guardar token
        file_put_contents(__DIR__ . '/gmail_token.json', json_encode($tokenData, JSON_PRETTY_PRINT));
        echo "<h2>✅ Autorización Exitosa</h2>";
        echo "<p>Gmail API configurado correctamente.</p>";
        echo "<a href='/api/test_gmail.php'>🔄 Probar Gmail API</a>";
    } else {
        echo "<h2>❌ Error en Autorización</h2>";
        echo "<pre>" . print_r($tokenData, true) . "</pre>";
    }
} else {
    echo "<h2>❌ No se recibió código de autorización</h2>";
}
?>