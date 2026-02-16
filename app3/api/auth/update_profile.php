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

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

// Conectar a BD laruta11 (NO app_db)
$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

$user_id = $_SESSION['user']['id'];
$telefono = $_POST['telefono'] ?? '';
$instagram = $_POST['instagram'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$genero = $_POST['genero'] ?? '';
$direccion = $_POST['direccion'] ?? '';
$lugar_nacimiento = $_POST['lugar_nacimiento'] ?? '';

// Validar género
$generos_validos = ['masculino', 'femenino', 'otro', 'no_decir'];
if ($genero && !in_array($genero, $generos_validos)) {
    $genero = '';
}

// Validar fecha de nacimiento
if ($fecha_nacimiento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_nacimiento)) {
    $fecha_nacimiento = null;
}

try {
    // Actualizar perfil del usuario
    $query = "UPDATE usuarios SET 
                telefono = ?, 
                instagram = ?, 
                direccion = ?,
                lugar_nacimiento = ?";
    
    $types = "ssss";
    $params = [$telefono, $instagram, $direccion, $lugar_nacimiento];
    
    if ($genero) {
        $query .= ", genero = ?";
        $types .= "s";
        $params[] = $genero;
    }
    
    if ($fecha_nacimiento) {
        $query .= ", fecha_nacimiento = ?";
        $types .= "s";
        $params[] = $fecha_nacimiento;
    }
    
    $query .= " WHERE id = ?";
    $types .= "i";
    $params[] = $user_id;
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error preparando consulta: ' . mysqli_error($conn)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    
    if (mysqli_stmt_execute($stmt)) {
        // Obtener datos actualizados del usuario
        $user_query = "SELECT * FROM usuarios WHERE id = ?";
        $user_stmt = mysqli_prepare($conn, $user_query);
        mysqli_stmt_bind_param($user_stmt, "i", $user_id);
        mysqli_stmt_execute($user_stmt);
        $result = mysqli_stmt_get_result($user_stmt);
        $updated_user = mysqli_fetch_assoc($result);
        
        // Actualizar sesión con datos nuevos
        $_SESSION['user'] = $updated_user;
        
        echo json_encode([
            'success' => true, 
            'message' => 'Perfil actualizado correctamente',
            'user' => $updated_user
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error actualizando perfil: ' . mysqli_error($conn)]);
    }
    
    mysqli_stmt_close($stmt);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

mysqli_close($conn);
?>