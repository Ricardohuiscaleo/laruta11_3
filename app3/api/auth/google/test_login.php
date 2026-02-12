<?php
session_start();
$config = require_once __DIR__ . '/../../../config.php';

$google_login_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'],
    'scope' => 'email profile',
    'response_type' => 'code',
    'access_type' => 'offline'
]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Google Login</title>
</head>
<body>
    <h1>Test Google OAuth</h1>
    <p>Client ID: <?= substr($config['ruta11_google_client_id'], 0, 20) ?>...</p>
    <p>Redirect URI: <?= $config['ruta11_google_redirect_uri'] ?></p>
    <a href="<?= $google_login_url ?>" style="background: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
        Login with Google
    </a>
</body>
</html>