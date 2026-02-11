<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$config_paths = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../../config.php',
    __DIR__ . '/../../../config.php',
    __DIR__ . '/../../../../config.php',
    __DIR__ . '/../../../../../config.php'
];

$config = null;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        $config = require_once $path;
        break;
    }
}

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Config no encontrado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'error' => 'Email y contraseña son obligatorios']);
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$config['app_db_host']};dbname={$config['app_db_name']};charset=utf8mb4",
        $config['app_db_user'],
        $config['app_db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'Email o contraseña incorrectos']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    error_log('🔍 [DEBUG] === LOGIN MANUAL EXITOSO ===');
    error_log('🔍 [DEBUG] Usuario: ' . $user['nombre'] . ' (' . $user['email'] . ')');
    
    // Configurar sesión persistente (30 días)
    ini_set('session.cookie_lifetime', 2592000);
    ini_set('session.gc_maxlifetime', 2592000);
    session_start();
    
    error_log('🔍 [DEBUG] Session ID creado: ' . session_id());
    error_log('🔍 [DEBUG] Session name: ' . session_name());
    
    // Renovar cookie de sesión
    if (isset($_COOKIE[session_name()])) {
        error_log('✅ [DEBUG] Cookie existe, renovando...');
        setcookie(session_name(), session_id(), time() + 2592000, '/', '', true, true);
    } else {
        error_log('🔍 [DEBUG] Creando nueva cookie de sesión...');
        setcookie(session_name(), session_id(), time() + 2592000, '/', '', true, true);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['nombre'];
    $_SESSION['user'] = $user; // Guardar usuario completo
    
    error_log('✅ [DEBUG] Sesión guardada: ' . json_encode($_SESSION['user']));
    error_log('🔍 [DEBUG] === LOGIN MANUAL FIN ===');
    
    echo json_encode([
        'success' => true,
        'message' => '¡Bienvenido de vuelta!',
        'user' => [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'telefono' => $user['telefono'],
            'foto_perfil' => $user['foto_perfil'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['nombre']) . '&background=f97316&color=fff',
            'total_orders' => $user['total_orders'],
            'total_spent' => $user['total_spent']
        ]
    ]);
    
} catch (Exception $e) {
    error_log('❌ [DEBUG] Error en login: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error al iniciar sesión']);
}
