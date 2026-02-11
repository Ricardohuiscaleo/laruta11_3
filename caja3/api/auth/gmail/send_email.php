<?php
function sendGmailEmail($email_data) {
    try {
        // Leer token de acceso
        $token_file = __DIR__ . '/gmail_token.json';
        if (!file_exists($token_file)) {
            return ['success' => false, 'error' => 'Token de Gmail no encontrado'];
        }
        
        $token_data = json_decode(file_get_contents($token_file), true);
        $access_token = $token_data['access_token'];
        
        // Crear mensaje de email
        $to = $email_data['to'];
        $subject = mb_convert_encoding($email_data['subject'], 'UTF-8', 'auto');
        $body = mb_convert_encoding($email_data['body'], 'UTF-8', 'auto');
        $from_name = $email_data['from_name'] ?? 'La Ruta 11';
        $candidate_name = $email_data['candidate_name'] ?? 'Estimado/a candidato/a';
        
        // Crear template HTML con tonos cálidos
        $html_body = createWarmEmailTemplate($body, $subject, $candidate_name);
        
        $message = "From: $from_name <saboresdelaruta11@gmail.com>\r\n";
        $message .= "To: $to\r\n";
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $html_body;
        
        // Codificar mensaje en base64
        $encoded_message = rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
        
        // Enviar via Gmail API
        $url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';
        
        $post_data = json_encode([
            'raw' => $encoded_message
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Error HTTP: ' . $http_code . ' - ' . $response];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function createWarmEmailTemplate($body, $subject, $candidate_name) {
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . htmlspecialchars($subject) . '</title>
    </head>
    <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #FFF8F0;">
        <div style="max-width: 600px; margin: 0 auto; background-color: #FFFFFF; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #D2691E 0%, #CD853F 100%); padding: 30px 20px; text-align: center;">
                <img src="https://ruta11app.agenterag.com/icon.png" alt="La Ruta 11" style="width: 50px; height: 50px; margin-bottom: 10px;">
                <h1 style="color: #FFFFFF; margin: 0; font-size: 28px; font-weight: bold; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">La Ruta 11</h1>
                <p style="color: #FFF8DC; margin: 5px 0 0 0; font-size: 16px;">Sabores que conectan</p>
            </div>
            
            <!-- Content -->
            <div style="padding: 40px 30px;">
                <h2 style="color: #8B4513; margin: 0 0 20px 0; font-size: 24px;">¡Hola ' . htmlspecialchars($candidate_name) . '! 👋</h2>
                
                <div style="background-color: #FFF8DC; border-left: 4px solid #D2691E; padding: 20px; margin: 20px 0; border-radius: 0 8px 8px 0;">
                    <p style="color: #8B4513; margin: 0; font-size: 16px; line-height: 1.6;">' . nl2br(htmlspecialchars($body)) . '</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <div style="background-color: #FFEFD5; padding: 20px; border-radius: 12px; border: 2px dashed #D2691E;">
                        <p style="color: #8B4513; margin: 0; font-size: 14px; font-style: italic;">"En La Ruta 11, somos un equipo unido que crea momentos gastronómicos inolvidables."</p>
                    </div>
                </div>
                
                <p style="color: #8B4513; font-size: 16px; line-height: 1.6; margin: 20px 0;">Si tienes alguna pregunta, no dudes en contactarnos. ¡Estamos aquí para apoyarte en este proceso!</p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="https://ruta11app.agenterag.com/jobs/" style="background: linear-gradient(135deg, #D2691E 0%, #CD853F 100%); color: #FFFFFF; padding: 12px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; display: inline-block; box-shadow: 0 4px 8px rgba(210,105,30,0.3);">🔗 Ver Portal de Empleos</a>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="background-color: #8B4513; padding: 25px 20px; text-align: center;">
                <img src="https://ruta11app.agenterag.com/icon.png" alt="La Ruta 11" style="width: 30px; height: 30px; margin-bottom: 5px;">
                <p style="color: #FFEFD5; margin: 0 0 10px 0; font-size: 18px; font-weight: bold;">La Ruta 11</p>
                <p style="color: #DEB887; margin: 0; font-size: 14px;">Arica, Chile</p>
                <div style="margin: 20px 0;">
                    <a href="https://www.facebook.com/laruta11" style="display: inline-block; margin: 0 5px; padding: 8px 16px; background-color: #1877F2; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;" title="Facebook">
                        Facebook
                    </a>
                    <a href="https://www.instagram.com/la_ruta_11/" style="display: inline-block; margin: 0 5px; padding: 8px 16px; background: linear-gradient(45deg, #f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%); color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;" title="Instagram">
                        Instagram
                    </a>
                    <a href="https://www.tiktok.com/@la.ruta.11?_t=ZM-8wUTDnMvZlI&_r=1" style="display: inline-block; margin: 0 5px; padding: 8px 16px; background-color: #000000; color: #FFFFFF; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;" title="TikTok">
                        TikTok
                    </a>
                </div>
                <p style="color: #DEB887; margin: 10px 0 0 0; font-size: 12px;">© 2025 La Ruta 11. Todos los derechos reservados.</p>
            </div>
        </div>
    </body>
    </html>';
}
?>