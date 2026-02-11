<?php
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../../config.php';
header('Content-Type: application/json');

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

$user_id = intval($_POST['user_id']);
$tipo = mysqli_real_escape_string($user_conn, $_POST['tipo']);
$titulo = mysqli_real_escape_string($user_conn, $_POST['titulo']);
$mensaje = mysqli_real_escape_string($user_conn, $_POST['mensaje']);

$query = "INSERT INTO user_notifications (user_id, tipo, titulo, mensaje) 
          VALUES ($user_id, '$tipo', '$titulo', '$mensaje')";

if (mysqli_query($user_conn, $query)) {
    echo json_encode(['success' => true, 'id' => mysqli_insert_id($user_conn)]);
} else {
    echo json_encode(['error' => 'Error enviando notificación']);
}

mysqli_close($user_conn);
?>