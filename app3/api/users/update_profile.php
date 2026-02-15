<?php
session_start();

// Buscar config en múltiples ubicaciones
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

if (!$config) {
    echo json_encode(['success' => false, 'error' => 'Configuración no encontrada']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Conectar a BD laruta11
$user_conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$user_conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($user_conn, 'utf8');
$user_id = $_SESSION['user']['id'];

$telefono = mysqli_real_escape_string($user_conn, $_POST['telefono'] ?? '');
$instagram = mysqli_real_escape_string($user_conn, $_POST['instagram'] ?? '');
$lugar_nacimiento = mysqli_real_escape_string($user_conn, $_POST['lugar_nacimiento'] ?? '');
$genero = mysqli_real_escape_string($user_conn, $_POST['genero'] ?? '');
$fecha_nacimiento = mysqli_real_escape_string($user_conn, $_POST['fecha_nacimiento'] ?? '');

$query = "UPDATE usuarios SET 
    telefono = '$telefono',
    instagram = '$instagram', 
    lugar_nacimiento = '$lugar_nacimiento',
    genero = '$genero',
    fecha_nacimiento = " . ($fecha_nacimiento ? "'$fecha_nacimiento'" : "NULL") . "
    WHERE id = $user_id";

if (mysqli_query($user_conn, $query)) {
    // Actualizar sesión
    $_SESSION['user']['telefono'] = $telefono;
    $_SESSION['user']['instagram'] = $instagram;
    $_SESSION['user']['lugar_nacimiento'] = $lugar_nacimiento;
    $_SESSION['user']['genero'] = $genero;
    $_SESSION['user']['fecha_nacimiento'] = $fecha_nacimiento;
    
    echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error actualizando perfil: ' . mysqli_error($user_conn)]);
}

mysqli_close($user_conn);
?>