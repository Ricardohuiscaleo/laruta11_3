<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Buscar config.php hasta 5 niveles
function findConfig() {
    $levels = ['', '../', '../../', '../../../', '../../../../', '../../../../../'];
    foreach ($levels as $level) {
        $configPath = __DIR__ . '/' . $level . 'config.php';
        if (file_exists($configPath)) {
            return $configPath;
        }
    }
    return null;
}

$configPath = findConfig();
if ($configPath) {
    $config = include $configPath;
    $inventarioUser = $config['inventario_user'] ?? 'inventario';
    $inventarioPassword = $config['inventario_password'] ?? 'Inv3nt4r10R11@2025';
} else {
    $inventarioUser = 'inventario';
    $inventarioPassword = 'Inv3nt4r10R11@2025';
}

$input = json_decode(file_get_contents('php://input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

// Usar credenciales específicas para inventario
$valid_users = [
    $inventarioUser => $inventarioPassword
];

if (isset($valid_users[$username]) && $valid_users[$username] === $password) {
    $token = bin2hex(random_bytes(32));
    
    // Guardar token en archivo temporal (en producción usar base de datos)
    $tokens_file = __DIR__ . '/tokens.json';
    $tokens = file_exists($tokens_file) ? json_decode(file_get_contents($tokens_file), true) : [];
    $tokens[$token] = [
        'username' => $username,
        'expires' => time() + (24 * 60 * 60) // 24 horas
    ];
    file_put_contents($tokens_file, json_encode($tokens));
    
    echo json_encode([
        'success' => true,
        'token' => $token,
        'username' => $username
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario o contraseña incorrectos'
    ]);
}
?>