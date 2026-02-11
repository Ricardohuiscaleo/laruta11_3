<?php
$config = require_once __DIR__ . '/../../../../../config.php';

echo "<h1>OAuth Configuration Check</h1>";
echo "<p><strong>Client ID:</strong> " . htmlspecialchars($config['ruta11_google_client_id']) . "</p>";
echo "<p><strong>Client Secret:</strong> " . htmlspecialchars(substr($config['ruta11_google_client_secret'], 0, 10)) . "...</p>";
echo "<p><strong>Redirect URI:</strong> " . htmlspecialchars($config['ruta11_google_redirect_uri']) . "</p>";

echo "<hr>";
echo "<h2>Test URLs</h2>";

$auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => $config['ruta11_google_redirect_uri'],
    'scope' => 'email profile',
    'response_type' => 'code',
    'access_type' => 'offline'
]);

echo "<p><a href='" . $auth_url . "'>Test OAuth with Config Values</a></p>";

// Test con simple_callback
$auth_url_debug = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $config['ruta11_google_client_id'],
    'redirect_uri' => 'https://app.laruta11.cl/api/auth/google/simple_callback.php',
    'scope' => 'email profile',
    'response_type' => 'code',
    'access_type' => 'offline'
]);

echo "<p><a href='" . $auth_url_debug . "'>Test OAuth with Debug Callback</a></p>";
?>