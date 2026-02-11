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

$user_id = $_SESSION['user']['id'];

try {
    $query = "SELECT * FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);
    
    if ($user) {
        // Actualizar sesión con datos frescos
        $_SESSION['user'] = $user;
        
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

mysqli_close($conn);
?>