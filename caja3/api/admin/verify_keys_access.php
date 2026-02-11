<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Buscar config.php hasta 5 niveles
function findConfig($startDir, $maxLevels = 5) {
    $currentDir = $startDir;
    for ($i = 0; $i < $maxLevels; $i++) {
        $configPath = $currentDir . '/config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
        $currentDir = dirname($currentDir);
    }
    return null;
}

$configPath = findConfig(__DIR__);
if (!$configPath) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuración no encontrada']);
    exit;
}

$config = require $configPath;

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña requeridos']);
    exit;
}

if (isset($config['admin_users'][$username]) && $config['admin_users'][$username] === $password) {
    $_SESSION['keys_admin'] = $username;
    echo json_encode(['success' => true, 'message' => 'Acceso autorizado']);
} else {
    echo json_encode(['success' => false, 'message' => 'Credenciales incorrectas']);
}
?>