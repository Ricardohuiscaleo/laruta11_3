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

// Verificar sesión
if (!isset($_SESSION['jobs_user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['jobs_user_id'];
$position = $_GET['position'] ?? 'maestro_sanguchero';

try {
    // Buscar datos del usuario para autocompletar (del último intento)
    $stmt = mysqli_prepare($conn, "SELECT * FROM job_applications WHERE user_id = ? AND position = ? ORDER BY created_at DESC LIMIT 1");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $position);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($row = mysqli_fetch_assoc($result)) {
        // Contar total de intentos
        $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM job_applications WHERE user_id = ? AND position = ?");
        mysqli_stmt_bind_param($count_stmt, "is", $user_id, $position);
        mysqli_stmt_execute($count_stmt);
        $count_result = mysqli_stmt_get_result($count_stmt);
        $count_row = mysqli_fetch_assoc($count_result);
        $total_attempts = $count_row['total'];
        
        echo json_encode([
            'success' => true,
            'has_previous' => true,
            'data' => [
                'nombre' => $row['nombre'],
                'telefono' => $row['telefono'],
                'instagram' => $row['instagram'],
                'nacionalidad' => $row['nacionalidad'],
                'genero' => $row['genero'],
                'requisitos_legales' => json_decode($row['requisitos_legales'], true),
                'curso_manipulador' => $row['curso_manipulador'],
                'curso_cajero' => $row['curso_cajero'],
                'total_attempts' => $total_attempts
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'has_incomplete' => false
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>