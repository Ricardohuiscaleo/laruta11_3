<?php
header('Content-Type: application/json');
session_start();

// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    die(json_encode(['success' => false, 'error' => 'Error de conexión a BD']));
}

mysqli_set_charset($conn, 'utf8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Verificar sesión
if (!isset($_SESSION['jobs_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['jobs_user_id'];
$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$instagram = trim($_POST['instagram'] ?? '');
$nacionalidad = $_POST['nacionalidad'] ?? '';
$genero = $_POST['genero'] ?? '';
$requisitos = $_POST['requisitos'] ?? [];
$curso_manipulador = $_POST['curso_manipulador'] ?? null;
$curso_cajero = $_POST['curso_cajero'] ?? null;
$position = $_POST['position'] ?? 'maestro_sanguchero';

// Validar campos obligatorios
if (empty($telefono)) {
    echo json_encode(['success' => false, 'error' => 'Teléfono/WhatsApp es obligatorio']);
    exit();
}
if (empty($nacionalidad)) {
    echo json_encode(['success' => false, 'error' => 'Nacionalidad es obligatoria']);
    exit();
}
if (empty($genero)) {
    echo json_encode(['success' => false, 'error' => 'Género es obligatorio']);
    exit();
}
if (count($requisitos) < 3) {
    echo json_encode(['success' => false, 'error' => 'Debes aceptar todos los requisitos legales']);
    exit();
}

try {
    // Actualizar datos del usuario
    $stmt = mysqli_prepare($conn, "UPDATE usuarios SET telefono = ?, instagram = ?, nacionalidad = ?, genero = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssssi", $telefono, $instagram, $nacionalidad, $genero, $user_id);
    mysqli_stmt_execute($stmt);
    
    // Contar intentos previos para este usuario y posición
    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM job_applications WHERE user_id = ? AND position = ?");
    mysqli_stmt_bind_param($count_stmt, "is", $user_id, $position);
    mysqli_stmt_execute($count_stmt);
    $count_result = mysqli_stmt_get_result($count_stmt);
    $count_row = mysqli_fetch_assoc($count_result);
    $attempt_number = $count_row['total'] + 1;
    
    // Crear nueva aplicación (siempre nuevo registro)
    $application_id = uniqid('app_', true);
    $requisitos_json = json_encode($requisitos);
    $stmt = mysqli_prepare($conn, "INSERT INTO job_applications (id, position, nombre, telefono, instagram, user_id, nacionalidad, genero, requisitos_legales, attempts, curso_manipulador, curso_cajero, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'started')");
    mysqli_stmt_bind_param($stmt, "sssssisssiss", $application_id, $position, $nombre, $telefono, $instagram, $user_id, $nacionalidad, $genero, $requisitos_json, $attempt_number, $curso_manipulador, $curso_cajero);
    mysqli_stmt_execute($stmt);
    
    echo json_encode([
        'success' => true,
        'application_id' => $application_id
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>