<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$userField = $input['email'] ?? $input['username'] ?? null;
$password = $input['password'] ?? null;

if (!$userField || !$password) {
    echo json_encode(['error' => 'Usuario/Email y contraseña son requeridos']);
    exit();
}

$email = filter_var(trim($userField), FILTER_VALIDATE_EMAIL);
if (!$email) {
    // If not email, search by username (nombre) or id
    $query = "SELECT id, email, nombre, password FROM usuarios WHERE nombre = ? OR email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "ss", $userField, $userField);
} else {
    // Search by email
    $query = "SELECT id, email, nombre, password FROM usuarios WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "s", $email);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['error' => 'Email o contraseña incorrectos']);
    exit();
}

// Iniciar sesión real para el navegador
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user'] = $user;

// Crear nuevo token
$token = bin2hex(random_bytes(32));
$update_query = "UPDATE usuarios SET session_token = ? WHERE id = ?";
$stmt = mysqli_prepare($conn, $update_query);
mysqli_stmt_bind_param($stmt, "si", $token, $user['id']);
mysqli_stmt_execute($stmt);

echo json_encode([
    'success' => true,
    'message' => 'Login exitoso',
    'token' => $token,
    'user' => [
        'id' => $user['id'],
        'email' => $user['email'],
        'nombre' => $user['nombre']
    ]
]);

mysqli_close($conn);
?>