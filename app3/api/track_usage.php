<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../config.php';

// Conectar a BD desde config central
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

header('Content-Type: application/json');

if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
    echo json_encode(['success' => false, 'error' => 'Usuario no autenticado']);
    exit();
}

$user_id = $_SESSION['user']['id'];
$action = $_POST['action'] ?? '';
$session_id = $_POST['session_id'] ?? '';

try {
    switch($action) {
        case 'start_session':
            // Insertar nueva sesión
            $query = "INSERT INTO user_metrics (user_id, session_id, session_start, last_activity) VALUES (?, ?, NOW(), NOW())";
            $stmt = mysqli_prepare($conn, $query);
            if (!$stmt) {
                throw new Exception('Error preparando consulta: ' . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($stmt, "is", $user_id, $session_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Error ejecutando consulta: ' . mysqli_error($conn));
            }
            
            // Actualizar contador de sesiones
            $update_query = "UPDATE usuarios SET total_sessions = COALESCE(total_sessions, 0) + 1 WHERE id = ?";
            $update_stmt = mysqli_prepare($conn, $update_query);
            mysqli_stmt_bind_param($update_stmt, "i", $user_id);
            mysqli_stmt_execute($update_stmt);
            break;
            
        case 'update_activity':
            $query = "UPDATE user_metrics SET last_activity = NOW() WHERE user_id = ? AND session_id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "is", $user_id, $session_id);
            mysqli_stmt_execute($stmt);
            break;
            
        case 'end_session':
            $query = "UPDATE user_metrics SET session_end = NOW(), total_time = TIMESTAMPDIFF(SECOND, session_start, NOW()) WHERE user_id = ? AND session_id = ? AND session_end IS NULL";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, "is", $user_id, $session_id);
            mysqli_stmt_execute($stmt);
            break;
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>