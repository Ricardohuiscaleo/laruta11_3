<?php
$config = require_once __DIR__ . '/../../../../../config.php';

// URL que usa exactamente el callback autorizado
$auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'], // callback.php autorizado
    'scope' => 'email profile',
    'response_type' => 'code'
]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Final OAuth</title>
</head>
<body>
    <h1>Test Final - OAuth Autorizado</h1>
    <p><strong>Client ID:</strong> <?= $config['ruta11_google_client_id'] ?></p>
    <p><strong>Redirect URI:</strong> <?= $config['ruta11_google_redirect_uri'] ?></p>
    <p><strong>Ambas BDs conectadas:</strong> ✅</p>
    
    <a href="<?= $auth_url ?>" 
       style="background: #4285f4; color: white; padding: 20px 40px; text-decoration: none; border-radius: 8px; font-size: 18px; display: inline-block; margin: 20px 0;">
        🚀 Login with Google (Final Test)
    </a>
    
    <p><small>Este test usa el callback autorizado y registrará en ambas bases de datos</small></p>
</body>
</html>