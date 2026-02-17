<?php
// Sistema de emails para VPS usando Gmail API existente

function sendEmailWithGmail($to, $subject, $message, $from_name = 'La Ruta 11') {
    try {
        $config_paths = [
            __DIR__ . '/../config.php',
            __DIR__ . '/../../config.php',
            __DIR__ . '/../../../config.php'
        ];
        
        $config = null;
        foreach ($config_paths as $path) {
            if (file_exists($path)) {
                $config = require_once $path;
                break;
            }
        }
        
        if (!$config) {
            throw new Exception('Config no encontrado');
        }
        
        // Usar token Gmail existente
        $token_file = __DIR__ . '/../gmail_token.json';
        
        if (!file_exists($token_file)) {
            throw new Exception('Token Gmail no encontrado');
        }
        
        $token_data = json_decode(file_get_contents($token_file), true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            throw new Exception('Token Gmail inválido');
        }
        
        // Verificar expiración
        if (time() >= ($token_data['expires_at'] ?? 0)) {
            throw new Exception('Token Gmail expirado');
        }
        
        // Crear mensaje RFC 2822
        $email_content = "To: $to\r\n";
        $email_content .= "From: $from_name <" . ($config['gmail_sender_email'] ?? 'noreply@laruta11.cl') . ">\r\n";
        $email_content .= "Subject: $subject\r\n";
        $email_content .= "Content-Type: text/html; charset=utf-8\r\n";
        $email_content .= "\r\n";
        $email_content .= $message;
        
        // Codificar base64 URL-safe
        $encoded_message = rtrim(strtr(base64_encode($email_content), '+/', '-_'), '=');
        
        // Enviar con Gmail API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token_data['access_token'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'raw' => $encoded_message
        ]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            error_log("Email enviado exitosamente a: $to - $subject");
            logEmail($to, $subject, 'sent');
            return true;
        } else {
            throw new Exception("Gmail API error: HTTP $http_code - $response");
        }
        
    } catch (Exception $e) {
        error_log("Error enviando email: " . $e->getMessage());
        logEmail($to, $subject, 'error', $e->getMessage());
        return false;
    }
}

// Función principal que reemplaza mail()
function sendEmail($to, $subject, $message, $headers = '') {
    return sendEmailWithGmail($to, $subject, $message);
}

// Función para logs de emails
function logEmail($to, $subject, $status, $error = null) {
    try {
        $config_paths = [
            __DIR__ . '/../config.php',
            __DIR__ . '/../../config.php',
            __DIR__ . '/../../../config.php'
        ];
        
        $config = null;
        foreach ($config_paths as $path) {
            if (file_exists($path)) {
                $config = require_once $path;
                break;
            }
        }
        
        if (!$config) return;
        
        $pdo = new PDO(
            "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
            $config['app_db_user'],
            $config['app_db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                method VARCHAR(50) DEFAULT 'gmail',
                status VARCHAR(50) NOT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (to_email, subject, status, error_message) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$to, $subject, $status, $error]);
        
    } catch (Exception $e) {
        error_log("Error logging email: " . $e->getMessage());
    }
}
?>