<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['tracker_user'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

$config = require_once __DIR__ . '/../../config.php';

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

$user_id = $_GET['user_id'] ?? '';

if (empty($user_id)) {
    echo json_encode(['success' => false, 'error' => 'User ID requerido']);
    exit();
}

try {
    // Obtener estado actual del usuario
    $user_query = "SELECT kanban_status FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conn, $user_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado con ID: ' . $user_id]);
        exit();
    }
    
    $notifications = []; // Simplificar por ahora
    
    echo json_encode([
        'success' => true,
        'data' => [
            'current_status' => $user['kanban_status'] ?? 'nuevo'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>