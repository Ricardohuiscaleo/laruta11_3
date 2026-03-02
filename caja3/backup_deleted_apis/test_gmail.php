<?php
header('Content-Type: text/html; charset=utf-8');

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Gmail API - La Ruta 11</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .btn:hover { background: #0056b3; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🔧 Test Gmail API - La Ruta 11</h1>
    
    <?php
    echo '<h2>📋 Estado del Sistema</h2>';
    
    // 1. Verificar token
    echo '<h3>1. Token Gmail</h3>';
    $tokenPath = __DIR__ . '/gmail_token.json';
    $hasValidToken = false;
    if (file_exists($tokenPath)) {
        $token = json_decode(file_get_contents($tokenPath), true);
        $hasValidToken = isset($token['access_token']);
        echo '<div class="status success">✅ gmail_token.json: Encontrado (' . ($hasValidToken ? 'Válido' : 'Inválido') . ')</div>';
    } else {
        echo '<div class="status error">❌ gmail_token.json: No encontrado</div>';
    }
    
    // 2. Verificar configuración
    echo '<h3>2. Configuración</h3>';
    $configPath = __DIR__ . '/../../../../../config.php';
    if (file_exists($configPath)) {
        $config = require $configPath;
        echo '<div class="status success">✅ Configuración: Gmail configurado correctamente<br><small>📧 Email: ' . $config['gmail_sender_email'] . '</small></div>';
    } else {
        echo '<div class="status error">❌ Configuración no encontrada</div>';
    }
    
    // 3. Test de envío
    echo '<h3>3. Test de Envío</h3>';
    if (isset($_POST['test_email'])) {
        try {
            require_once __DIR__ . '/test_send.php';
            $testEmail = $_POST['email'] ?? 'test@example.com';
            $htmlBody = '<h2>Test Gmail API</h2><p>Este es un email de prueba desde <strong>La Ruta 11</strong></p>';
            
            $result = testGmailSend($testEmail, 'Test Gmail API - La Ruta 11', $htmlBody);
            
            if ($result['success']) {
                echo '<div class="status success">✅ Email enviado correctamente a: ' . htmlspecialchars($testEmail) . '</div>';
            } else {
                echo '<div class="status error">❌ Error: ' . ($result['error'] ?? 'Error desconocido') . '</div>';
            }
        } catch (Exception $e) {
            echo '<div class="status error">❌ Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
    
    echo '<form method="POST">';
    echo '<input type="email" name="email" placeholder="tu-email@gmail.com" required style="padding: 10px; width: 300px;">';
    echo '<button type="submit" name="test_email" class="btn">📧 Enviar Email de Prueba</button>';
    echo '</form>';
    
    // 4. Acciones
    echo '<h3>4. Acciones</h3>';
    echo '<a href="gmail_setup.php" class="btn" style="background: #4285f4;">🔐 Reautorizar Gmail</a>';
    echo '<a href="?clear_token=1" class="btn" style="background: #dc3545;">🗑️ Limpiar Token</a>';
    
    // Limpiar token
    if (isset($_GET['clear_token']) && file_exists($tokenPath)) {
        unlink($tokenPath);
        echo '<div class="status info">🗑️ Token eliminado. <a href="gmail_setup.php">Reautorizar</a></div>';
        echo '<meta http-equiv="refresh" content="2">';
    }
    
    
    <hr>
    <p><small>💡 <strong>Tip:</strong> Si hay errores, limpia el token y reautoriza.</small></p>
</body>
</html>