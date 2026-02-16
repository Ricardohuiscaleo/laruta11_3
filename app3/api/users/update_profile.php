<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['success' => false, 'error' => 'No autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$config_file = __DIR__ . '/../../config.php';
$config = file_exists($config_file) ? require_once $config_file : [];

$db_host = $config['app_db_host'] ?? 'localhost';
$db_user = $config['app_db_user'] ?? 'root';
$db_pass = $config['app_db_pass'] ?? '';
$db_name = $config['app_db_name'] ?? 'laruta11';

$user_conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

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
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
$direccion = mysqli_real_escape_string($user_conn, $_POST['direccion'] ?? '');

// Validar género (debe ser uno de los valores del ENUM)
$generos_validos = ['masculino', 'femenino', 'otro', 'no_decir'];
if ($genero && !in_array($genero, $generos_validos)) {
    $genero = '';
}

// Validar fecha
if ($fecha_nacimiento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
    $fecha_nacimiento = null;
} elseif (empty($fecha_nacimiento) || $fecha_nacimiento === '0000-00-00') {
    $fecha_nacimiento = null;
}

$query = "UPDATE usuarios SET 
    telefono = '$telefono',
    instagram = '$instagram', 
    lugar_nacimiento = '$lugar_nacimiento',
    direccion = '$direccion'";

if ($genero) {
    $query .= ", genero = '$genero'";
}

if ($fecha_nacimiento) {
    $query .= ", fecha_nacimiento = '$fecha_nacimiento'";
} else {
    $query .= ", fecha_nacimiento = NULL";
}

$query .= " WHERE id = $user_id";

if (mysqli_query($user_conn, $query)) {
    // Actualizar sesión
    $_SESSION['user']['telefono'] = $telefono;
    $_SESSION['user']['instagram'] = $instagram;
    $_SESSION['user']['lugar_nacimiento'] = $lugar_nacimiento;
    $_SESSION['user']['genero'] = $genero;
    $_SESSION['user']['fecha_nacimiento'] = $fecha_nacimiento;
    $_SESSION['user']['direccion'] = $direccion;
    
    echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error actualizando perfil: ' . mysqli_error($user_conn)]);
}

mysqli_close($user_conn);
?>