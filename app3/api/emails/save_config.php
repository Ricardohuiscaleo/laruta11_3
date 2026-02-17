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
    
    $config = [
        'sender_email' => $input['sender_email'] ?? '',
        'smtp_host' => $input['smtp_host'] ?? '',
        'smtp_port' => $input['smtp_port'] ?? 587,
        'smtp_encryption' => $input['smtp_encryption'] ?? 'tls',
        'smtp_user' => $input['smtp_user'] ?? '',
        'smtp_pass' => $input['smtp_pass'] ?? ''
    ];
    
    // Guardar configuración en archivo JSON
    $config_file = __DIR__ . '/../../email_config.json';
    
    if (file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT))) {
        echo json_encode([
            'success' => true,
            'message' => 'Configuración guardada correctamente'
        ]);
    } else {
        throw new Exception('Error escribiendo archivo de configuración');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>