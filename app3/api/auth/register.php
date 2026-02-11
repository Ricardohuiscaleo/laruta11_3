<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a ambas bases de datos
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

$app_conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn || !$app_conn) {
    echo json_encode(['error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');
mysqli_set_charset($app_conn, 'utf8');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['email']) || !isset($input['password']) || !isset($input['nombre'])) {
    echo json_encode(['error' => 'Email, contraseña y nombre son requeridos']);
    exit();
}

$email = filter_var(trim($input['email']), FILTER_VALIDATE_EMAIL);
$password = trim($input['password']);
$nombre = trim($input['nombre']);

if (!$email) {
    echo json_encode(['error' => 'Email inválido']);
    exit();
}

if (strlen($password) < 6) {
    echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']);
    exit();
}

// Verificar si el email ya existe
$check_query = "SELECT id FROM usuarios WHERE email = ?";
$stmt = mysqli_prepare($conn, $check_query);
mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_fetch_assoc($result)) {
    echo json_encode(['error' => 'El email ya está registrado']);
    exit();
}

// Crear usuario con google_id único
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$google_id = 'manual_' . uniqid();
$token = bin2hex(random_bytes(32));

$insert_query = "INSERT INTO usuarios (email, password, nombre, activo, google_id, session_token) VALUES (?, ?, ?, 1, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "sssss", $email, $hashedPassword, $nombre, $google_id, $token);

if (mysqli_stmt_execute($stmt)) {
    $userId = mysqli_insert_id($conn);
    
    // Crear también en app_users
    $app_insert_query = "INSERT INTO app_users (google_id, email, name, password_hash, auth_provider, is_active, created_at) VALUES (?, ?, ?, ?, 'manual', 1, NOW())";
    $app_stmt = mysqli_prepare($app_conn, $app_insert_query);
    mysqli_stmt_bind_param($app_stmt, "ssss", $google_id, $email, $nombre, $hashedPassword);
    mysqli_stmt_execute($app_stmt);
    
    echo json_encode([
        'success' => true,
        'message' => 'Usuario registrado exitosamente',
        'token' => $token,
        'user' => [
            'id' => $userId,
            'email' => $email,
            'nombre' => $nombre
        ]
    ]);
} else {
    echo json_encode(['error' => 'Error al crear usuario: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
mysqli_close($app_conn);
?>