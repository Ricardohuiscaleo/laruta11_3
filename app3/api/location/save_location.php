<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Método no permitido']);
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

$latitud = floatval($_POST['latitud']);
$longitud = floatval($_POST['longitud']);
$direccion = mysqli_real_escape_string($user_conn, $_POST['direccion'] ?? '');
$precision = intval($_POST['precision'] ?? 0);

// Actualizar ubicación actual del usuario
$update_user = "UPDATE usuarios SET 
    latitud = $latitud, 
    longitud = $longitud, 
    direccion_actual = '$direccion',
    ubicacion_actualizada = NOW() 
    WHERE id = $user_id";

// Guardar en historial
$insert_history = "INSERT INTO user_locations (user_id, latitud, longitud, direccion, precision_metros) 
                   VALUES ($user_id, $latitud, $longitud, '$direccion', $precision)";

if (mysqli_query($user_conn, $update_user) && mysqli_query($user_conn, $insert_history)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Error guardando ubicación']);
}

mysqli_close($user_conn);
?>