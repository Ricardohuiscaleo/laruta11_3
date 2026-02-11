<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';

// Conectar a BD desde config central (u958525313_app)
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

$user_id = $_SESSION['user']['id'];
$telefono = $_POST['telefono'] ?? '';
$instagram = $_POST['instagram'] ?? '';
$fecha_nacimiento = $_POST['fecha_nacimiento'] ?? null;
$genero = $_POST['genero'] ?? '';
$direccion = $_POST['direccion'] ?? '';

// Validar fecha de nacimiento
if ($fecha_nacimiento && !DateTime::createFromFormat('Y-m-d', $fecha_nacimiento)) {
    echo json_encode(['success' => false, 'error' => 'Formato de fecha inválido']);
    exit();
}

try {
    // Verificar que la tabla existe y tiene las columnas
    $check_query = "SHOW COLUMNS FROM usuarios LIKE 'telefono'";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) == 0) {
        echo json_encode(['success' => false, 'error' => 'Columna telefono no existe']);
        exit();
    }
    
    // Actualizar perfil del usuario
    $query = "UPDATE usuarios SET 
                telefono = ?, 
                instagram = ?, 
                fecha_nacimiento = ?, 
                genero = ?,
                direccion = ?
              WHERE id = ?";
    
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'error' => 'Error preparando consulta: ' . mysqli_error($conn)]);
        exit();
    }
    
    mysqli_stmt_bind_param($stmt, "sssssi", $telefono, $instagram, $fecha_nacimiento, $genero, $direccion, $user_id);
    
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