<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';
$client_id = $config['gmail_client_id'];
$redirect_uri = $config['gmail_redirect_uri'];

// URL de autorización OAuth
$authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'https://www.googleapis.com/auth/gmail.send',
    'response_type' => 'code',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'approval_prompt' => 'force'
]);

echo "<h2>🔧 Configurar OAuth Gmail</h2>";
echo "<p>Para enviar emails desde saboresdelaruta11@gmail.com:</p>";
echo "<ol>";
echo "<li>Las credenciales ya están configuradas en el servidor</li>";
echo "<li>Haz clic en el botón de autorización</li>";
echo "<li>Acepta los permisos en Google</li>";
echo "</ol>";
echo "<a href='{$authUrl}' target='_blank' style='background: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; display: inline-block;'>🔐 Autorizar Gmail</a><br>";
echo "<a href='/api/test_gmail.php' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 10px 0; display: inline-block;'>🧪 Probar API</a>";
echo "<br><small>📧 Email configurado: " . $config['gmail_sender_email'] . "</small>";
