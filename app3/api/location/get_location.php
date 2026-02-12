<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

// Conectar a BD correcta (u958525313_app)
$user_conn = mysqli_connect(
    $config['app_db_host'],
    $config['app_db_user'],
    $config['app_db_pass'],
    $config['app_db_name']
);
$user_id = $_SESSION['user']['id'];

$query = "SELECT latitud, longitud, direccion_actual, ubicacion_actualizada 
          FROM usuarios WHERE id = $user_id";
$result = mysqli_query($user_conn, $query);
$location = mysqli_fetch_assoc($result);

mysqli_close($user_conn);

echo json_encode($location);
?>