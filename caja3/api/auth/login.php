<?php
require_once __DIR__ . '/../session_config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$config_paths = [
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
$config_path_used = '';
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require $path;
        $config_path_used = $path;
        break;
    }
}

if (!$config || !is_array($config)) {
    echo json_encode(['success' => false, 'message' => 'Configuración no encontrada o inválida']);
    exit;
}

$valid_users = $config['caja_users'] ?? [];

$input = json_decode(file_get_contents('php://input'), true);
$username = trim($input['username'] ?? '');
$password = trim($input['password'] ?? '');

if (isset($valid_users[$username]) && $valid_users[$username] === $password) {
    // Regenerar session_id por seguridad
    session_regenerate_id(true);
    
    // Guardar usuario en sesión
    $_SESSION['cashier'] = [
        'username' => $username,
        'login_time' => time()
    ];
    
    echo json_encode([
        'success' => true,
        'user' => $username,
        'message' => 'Login exitoso'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario o contraseña incorrectos'
    ]);
}
?>