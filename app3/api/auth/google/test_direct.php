<?php
$config = require_once __DIR__ . '/../../../config.php';

// URL exacta del config
$auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'], // Usa exactamente la del config
    'scope' => 'email profile',
    'response_type' => 'code'
]);

echo "<h1>Test con URL exacta del config</h1>";
echo "<p>Redirect URI: " . $config['ruta11_google_redirect_uri'] . "</p>";
echo "<a href='" . $auth_url . "' style='background: #4285f4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px;'>Login with Google</a>";
?>