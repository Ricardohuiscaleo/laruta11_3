<?php
session_start();

// Cargar config
$config = require_once __DIR__ . '/../config.php';

// Conectar a BD
$conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);

mysqli_set_charset($conn, 'utf8');
header('Content-Type: application/json');

// Obtener usuario ID 92
$query = "SELECT * FROM usuarios WHERE id = 92";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

if ($user) {
    // Simular sesión
    $_SESSION['user'] = $user;
    
    echo json_encode([
        'success' => true,
        'message' => 'Sesión simulada para usuario ID 92',
        'user' => [
            'id' => $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'es_militar_rl6' => $user['es_militar_rl6'],
            'credito_aprobado' => $user['credito_aprobado'],
            'grado_militar' => $user['grado_militar'],
            'limite_credito' => $user['limite_credito'],
            'credito_usado' => $user['credito_usado'],
            'credito_disponible' => $user['limite_credito'] - $user['credito_usado']
        ]
    ], JSON_PRETTY_PRINT);
} else {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
}

mysqli_close($conn);
?>
