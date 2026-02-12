<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Cargar credenciales desde config.php
$config = require_once __DIR__ . '/../config.php';
$admin_users = $config['admin_users'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $username = $input['username'] ?? '';
        $password = $input['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            throw new Exception('Usuario y contraseña requeridos');
        }
        
        // Verificar credenciales
        if (isset($admin_users[$username]) && $admin_users[$username] === $password) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = $username;
            $_SESSION['admin_login_time'] = time();
            
            echo json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'user' => $username
            ]);
        } else {
            throw new Exception('Credenciales incorrectas');
        }
    } else {
        throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>