<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
    exit();
}

// Conectar a BD desde config central
$user_conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$user_conn) {
    echo json_encode(['error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($user_conn, 'utf8');
$user_id = $_SESSION['user']['id'];

// Eliminar usuario (las foreign keys eliminarán automáticamente datos relacionados)
$query = "DELETE FROM usuarios WHERE id = $user_id";

if (mysqli_query($user_conn, $query)) {
    session_destroy();
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Error eliminando cuenta']);
}

mysqli_close($user_conn);
?>