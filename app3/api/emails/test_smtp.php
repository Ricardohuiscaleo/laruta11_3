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
    
    $host = $input['host'] ?? '';
    $port = $input['port'] ?? 587;
    $encryption = $input['encryption'] ?? 'tls';
    $user = $input['user'] ?? '';
    $pass = $input['pass'] ?? '';
    
    if (empty($host) || empty($user) || empty($pass)) {
        throw new Exception('Faltan datos de configuración SMTP');
    }
    
    // Probar conexión SMTP
    $socket = fsockopen($host, $port, $errno, $errstr, 10);
    
    if (!$socket) {
        throw new Exception("No se pudo conectar a $host:$port - $errstr");
    }
    
    // Leer respuesta inicial
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '220') {
        fclose($socket);
        throw new Exception('Respuesta SMTP inválida: ' . trim($response));
    }
    
    // Enviar EHLO
    fwrite($socket, "EHLO laruta11.cl\r\n");
    $response = fgets($socket, 512);
    if (substr($response, 0, 3) !== '250') {
        fclose($socket);
        throw new Exception('Error en EHLO: ' . trim($response));
    }
    
    // Si usa TLS, iniciar STARTTLS
    if ($encryption === 'tls') {
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 512);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            throw new Exception('Error en STARTTLS: ' . trim($response));
        }
    }
    
    fclose($socket);
    
    echo json_encode([
        'success' => true,
        'message' => 'Conexión SMTP exitosa',
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>