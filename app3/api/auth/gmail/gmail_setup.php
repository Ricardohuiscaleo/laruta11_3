<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Cargar config desde raíz
    $config = require_once __DIR__ . '/../../../../../config.php';
    $client_id = $config['gmail_client_id'];
    $redirect_uri = $config['gmail_redirect_uri'];
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}

// URL de autorización OAuth
$authUrl = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query([
    'client_id' => $client_id,
    'redirect_uri' => $redirect_uri,
    'scope' => 'https://www.googleapis.com/auth/gmail.send',
    'response_type' => 'code',
    'access_type' => 'offline',
    'prompt' => 'consent'
]);

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar Gmail OAuth - La Ruta 11</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; text-align: center; }
        .btn { background: #4285f4; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px; }
        .btn:hover { background: #357ae8; }
        .info { background: #e7f3ff; padding: 20px; border-radius: 5px; margin: 20px 0; }
    </style>
</head>
<body>
    <h1>🔧 Configurar Gmail OAuth</h1>
    
    <div class="info">
        <h3>Para enviar emails desde saboresdelaruta11@gmail.com:</h3>
        <ol style="text-align: left;">
            <li>Haz clic en "Autorizar Gmail"</li>
            <li>Inicia sesión con saboresdelaruta11@gmail.com</li>
            <li>Acepta todos los permisos</li>
            <li>Serás redirigido automáticamente</li>
        </ol>
    </div>
    
    <a href="<?php echo $authUrl; ?>" class="btn">🔐 Autorizar Gmail</a>
    
    <br><br>
    <a href="/api/test_gmail.php" style="color: #666;">← Volver al test</a>
    
    <div style="margin-top: 30px; font-size: 12px; color: #666;">
        📧 Email configurado: <?php echo $config['gmail_sender_email']; ?>
    </div>
</body>
</html>