<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación
if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

// Cargar config
$config = require_once __DIR__ . '/../../config.php';

// Conectar a BD
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

$input = json_decode(file_get_contents('php://input'), true);
$candidate_id = $input['candidate_id'] ?? '';
$position = $input['position'] ?? '';

if (empty($candidate_id)) {
    echo json_encode(['success' => false, 'error' => 'ID de candidato requerido']);
    exit();
}

try {
    // Obtener datos del candidato
    $query = "
        SELECT ja.nombre, ja.position, u.email, MAX(ja.score) as best_score, MAX(ja.completed_at) as completed_at
        FROM job_applications ja
        LEFT JOIN usuarios u ON ja.user_id = u.id
        WHERE ja.user_id = ? AND ja.position = ?
        GROUP BY ja.user_id, ja.position
    ";
    
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $candidate_id, $position);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = mysqli_fetch_assoc($result);
    
    if (!$candidate || !$candidate['email']) {
        echo json_encode(['success' => false, 'error' => 'Candidato no encontrado o sin email']);
        exit();
    }
    
    // Preparar datos del email
    $positionName = $position === 'maestro_sanguchero' ? 'Maestro/a Sanguchero/a' : 'Cajero/a';
    $score = round($candidate['best_score'] ?: 0);
    $completedDate = date('d/m/Y', strtotime($candidate['completed_at']));
    
    // Debug del score
    error_log("[EMAIL DEBUG] Score original: {$candidate['best_score']}, Score redondeado: {$score}");
    
    // Generar HTML del email
    $emailHtml = generateEmailHTML($candidate['nombre'], $positionName, $score, $completedDate);
    
    // Log datos del candidato
    error_log("[EMAIL DEBUG] Candidato: {$candidate['nombre']}, Email: {$candidate['email']}, Posición: {$positionName}");
    
    // Enviar email usando Gmail API
    $emailResult = sendGmailEmail(
        $candidate['email'],
        $candidate['nombre'],
        "Hemos revisado tu postulación en La Ruta 11",
        $emailHtml,
        $config
    );
    
    if ($emailResult['success']) {
        error_log("[EMAIL SUCCESS] Email enviado a {$candidate['email']}");
        echo json_encode(['success' => true, 'message' => 'Email enviado correctamente']);
    } else {
        error_log("[EMAIL ERROR] {$emailResult['error']}");
        echo json_encode(['success' => false, 'error' => $emailResult['error']]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);

// Función para generar HTML del email
function generateEmailHTML($nombre, $posicion, $score, $fecha) {
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .score-badge { display: inline-block; background: #28a745; color: white; padding: 10px 20px; border-radius: 25px; font-weight: bold; font-size: 18px; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
            .logo { width: 60px; height: 60px; margin: 0 auto 15px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo'><img src='https://ruta11app.agenterag.com/icon.png' alt='La Ruta 11' style='width: 60px; height: 60px; border-radius: 10px;'></div>
                <h1>La Ruta 11</h1>
                <h2>¡Hemos revisado tu postulación!</h2>
            </div>
            
            <div class='content'>
                <h3>Hola {$nombre},</h3>
                
                <p>Esperamos que te encuentres muy bien. Te escribimos para informarte que <strong>hemos revisado tu postulación</strong> para el cargo de <strong>{$posicion}</strong> en la Ruta 11 Foodtrucks.</p>
                
                <div style='text-align: center; margin: 25px 0;'>
                    <div class='score-badge'>Tu evaluación: {$score}%</div>
                </div>
                
                <p><strong>Detalles de tu postulación:</strong></p>
                <ul>
                    <li><strong>Posición:</strong> {$posicion}</li>
                    <li><strong>Fecha de postulación:</strong> {$fecha}</li>
                    <li><strong>Estado:</strong> Revisada por nuestro equipo</li>
                </ul>
                
                <p>Nuestro equipo de recursos humanos está evaluando cuidadosamente todas las postulaciones recibidas. Si tu perfil se ajusta a lo que estamos buscando, <strong>nos pondremos en contacto contigo en los próximos días</strong>.</p>
                
                <p>Mientras tanto, te invitamos a seguir nuestras redes sociales para conocer más sobre La Ruta 11 y nuestro ambiente de trabajo:</p>
                
                <p style='text-align: center; margin: 25px 0;'>
                    <a href='https://instagram.com/la_ruta_11' style='color: #667eea; text-decoration: none;'>📱 @la_ruta_11</a>
                </p>
                
                <p>Agradecemos sinceramente tu interés en formar parte de nuestro equipo. La Ruta 11 es más que una cadena de Foodtrucks, somos un equipo apasionado que trabaja para crear la mejor experiencia gastronómica en el norte de Chile.</p>
                
                <p>¡Gracias por confiar en nosotros!</p>
                
                <p style='margin-top: 30px;'>
                    <strong>Equipo de Recursos Humanos</strong><br>
                    La Ruta 11<br>
                    <em>\"Sabores que conectan\"</em>
                </p>
            </div>
            
            <div class='footer'>
                <p>Este email fue enviado desde nuestro sistema de gestión de candidatos.</p>
                <p>La Ruta 11 Foodtrucks- Arica, Chile</p>
            </div>
        </div>
    </body>
    </html>
    ";
}

// Función para enviar email usando Gmail API
function sendGmailEmail($to, $toName, $subject, $htmlBody, $config) {
    // Auto-renovar token si es necesario
    require_once __DIR__ . '/../auth/gmail/auto_refresh.php';
    if (!ensureValidGmailToken()) {
        error_log("[EMAIL ERROR] No se pudo renovar el token");
        return ['success' => false, 'error' => 'Token OAuth expirado y no se pudo renovar'];
    }
    
    // Verificar si existe token
    $tokenFile = __DIR__ . '/../auth/gmail/gmail_token.json';
    if (!file_exists($tokenFile)) {
        error_log("[EMAIL ERROR] Token file not found: {$tokenFile}");
        return ['success' => false, 'error' => 'Token OAuth no encontrado. Configura Gmail OAuth primero.'];
    }
    
    $tokenData = json_decode(file_get_contents($tokenFile), true);
    if (!$tokenData || !isset($tokenData['access_token'])) {
        error_log("[EMAIL ERROR] Invalid token data");
        return ['success' => false, 'error' => 'Token OAuth inválido'];
    }
    
    error_log("[EMAIL DEBUG] Preparando email para: {$to}");
    
    // Preparar email
    $boundary = uniqid(rand(), true);
    $rawMessage = "To: {$toName} <{$to}>\r\n";
    $rawMessage .= "From: La Ruta 11 <{$config['gmail_sender_email']}>\r\n";
    $rawMessage .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $rawMessage .= "MIME-Version: 1.0\r\n";
    $rawMessage .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n\r\n";
    $rawMessage .= "--{$boundary}\r\n";
    $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $rawMessage .= $htmlBody . "\r\n";
    $rawMessage .= "--{$boundary}--";
    
    $encodedMessage = base64_encode($rawMessage);
    $encodedMessage = str_replace(['+', '/', '='], ['-', '_', ''], $encodedMessage);
    
    error_log("[EMAIL DEBUG] Enviando a Gmail API...");
    
    // Enviar usando Gmail API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['raw' => $encodedMessage]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tokenData['access_token'],
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("[EMAIL DEBUG] HTTP Code: {$httpCode}, Response: {$response}");
    
    if ($curlError) {
        error_log("[EMAIL ERROR] CURL Error: {$curlError}");
        return ['success' => false, 'error' => "Error de conexión: {$curlError}"];
    }
    
    if ($httpCode === 200) {
        return ['success' => true];
    } else {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? "HTTP {$httpCode}";
        error_log("[EMAIL ERROR] Gmail API Error: {$errorMsg}");
        return ['success' => false, 'error' => "Gmail API: {$errorMsg}"];
    }
}
?>