<?php
session_start();
header('Content-Type: application/json');

// Verificar autenticación y permisos de admin
if (!isset($_SESSION['tracker_user']) || $_SESSION['tracker_user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit();
}

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
    echo json_encode(['success' => false, 'error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $result = mysqli_query($conn, "SELECT id, email, nombre, role, active, created_at FROM tracker_authorized_users ORDER BY role, nombre");
            $users = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $users[] = $row;
            }
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'add':
            $email = $_POST['email'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $role = $_POST['role'] ?? 'viewer';
            
            if (empty($email) || empty($nombre)) {
                echo json_encode(['success' => false, 'error' => 'Email y nombre son requeridos']);
                break;
            }
            
            $stmt = mysqli_prepare($conn, "INSERT INTO tracker_authorized_users (email, nombre, role) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sss", $email, $nombre, $role);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Usuario agregado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al agregar usuario']);
            }
            break;
            
        case 'update':
            $id = $_POST['id'] ?? '';
            $nombre = $_POST['nombre'] ?? '';
            $role = $_POST['role'] ?? '';
            $active = isset($_POST['active']) ? (bool)$_POST['active'] : true;
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            $stmt = mysqli_prepare($conn, "UPDATE tracker_authorized_users SET nombre = ?, role = ?, active = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "ssii", $nombre, $role, $active, $id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar usuario']);
            }
            break;
            
        case 'delete':
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID requerido']);
                break;
            }
            
            // No permitir eliminar al usuario actual
            if ($id == $_SESSION['tracker_user']['db_id']) {
                echo json_encode(['success' => false, 'error' => 'No puedes eliminarte a ti mismo']);
                break;
            }
            
            $stmt = mysqli_prepare($conn, "DELETE FROM tracker_authorized_users WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Usuario eliminado correctamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al eliminar usuario']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}

mysqli_close($conn);
?>