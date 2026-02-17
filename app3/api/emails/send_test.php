<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar autenticación admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $to = $input['to'] ?? '';
    $subject = $input['subject'] ?? '';
    $message = $input['message'] ?? '';
    $method = $input['method'] ?? 'gmail';
    
    if (empty($to) || empty($subject) || empty($message)) {
        throw new Exception('Faltan datos del email');
    }
    
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email de destino inválido');
    }
    
    if ($method === 'gmail') {
        $result = sendWithGmail($to, $subject, $message);
    } else {
        $result = sendWithSMTP($to, $subject, $message);
    }
    
    if ($result['success']) {
        // Log del email enviado
        logEmail($to, $subject, $method, 'sent');
        
        echo json_encode([
            'success' => true,
            'message' => 'Email enviado correctamente',
            'method' => $method
        ]);
    } else {
        // Log del error
        logEmail($to, $subject, $method, 'error', $result['error']);
        
        throw new Exception($result['error']);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function sendWithGmail($to, $subject, $message) {
    try {
        $token_file = __DIR__ . '/../../gmail_token.json';
        
        if (!file_exists($token_file)) {
            return ['success' => false, 'error' => 'Token de Gmail no encontrado'];
        }
        
        $token_data = json_decode(file_get_contents($token_file), true);
        
        if (!$token_data || !isset($token_data['access_token'])) {
            return ['success' => false, 'error' => 'Token de Gmail inválido'];
        }
        
        // Crear mensaje RFC 2822
        $email_message = "To: $to\r\n";
        $email_message .= "Subject: $subject\r\n";
        $email_message .= "Content-Type: text/html; charset=utf-8\r\n";
        $email_message .= "\r\n";
        $email_message .= $message;
        
        // Codificar en base64 URL-safe
        $encoded_message = rtrim(strtr(base64_encode($email_message), '+/', '-_'), '=');
        
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
        
        if ($http_code !== 200) {
            return ['success' => false, 'error' => 'Error Gmail API (HTTP ' . $http_code . ')'];
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function sendWithSMTP($to, $subject, $message) {
    // Implementación básica SMTP (se puede mejorar)
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=utf-8\r\n";
    $headers .= "From: noreply@laruta11.cl\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => 'Error enviando con mail()'];
    }
}

function logEmail($to, $subject, $method, $status, $error = null) {
    try {
        $config_paths = [
            __DIR__ . '/../../config.php',
            __DIR__ . '/../../../config.php',
            __DIR__ . '/../../../../config.php'
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
        
        // Crear tabla si no existe
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS email_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                method VARCHAR(50) NOT NULL,
                status VARCHAR(50) NOT NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $pdo->prepare("
            INSERT INTO email_logs (to_email, subject, method, status, error_message) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$to, $subject, $method, $status, $error]);
        
    } catch (Exception $e) {
        error_log("Error logging email: " . $e->getMessage());
    }
}
?>