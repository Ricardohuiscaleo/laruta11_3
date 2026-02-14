<?php
require_once __DIR__ . '/../session_config.php';

// Solo admin puede acceder
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$config = require_once __DIR__ . '/../../config.php';

$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Error de conexión']);
    exit;
}

mysqli_set_charset($conn, 'utf8mb4');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id']);
    $rut = mysqli_real_escape_string($conn, $_POST['rut']);
    $grado_militar = mysqli_real_escape_string($conn, $_POST['grado_militar']);
    $unidad = mysqli_real_escape_string($conn, $_POST['unidad']);
    $anos_servicio = intval($_POST['anos_servicio']);
    $direccion = mysqli_real_escape_string($conn, $_POST['direccion']);
    $comuna = mysqli_real_escape_string($conn, $_POST['comuna']);
    $region = mysqli_real_escape_string($conn, $_POST['region']);
    
    // Actualizar usuario
    $sql = "UPDATE usuarios SET 
            es_militar_rl6 = 1,
            rut = '$rut',
            grado_militar = '$grado_militar',
            credito_aprobado = 0
            WHERE id = $user_id";
    
    if (mysqli_query($conn, $sql)) {
        // Insertar datos adicionales en tabla rl6_solicitudes
        $sql2 = "INSERT INTO rl6_solicitudes 
                (user_id, unidad, anos_servicio, direccion, comuna, region, estado, created_at) 
                VALUES 
                ($user_id, '$unidad', $anos_servicio, '$direccion', '$comuna', '$region', 'pendiente', NOW())";
        
        mysqli_query($conn, $sql2);
        
        echo json_encode([
            'success' => true,
            'message' => 'Datos registrados exitosamente'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar: ' . mysqli_error($conn)
        ]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
}

mysqli_close($conn);
?>
