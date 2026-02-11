<?php
session_start();
// Cargar config desde raíz
require_once '../../config.php';
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
$address_id = intval($_POST['address_id']);

$query = "DELETE FROM user_addresses WHERE id = $address_id AND user_id = $user_id";

if (mysqli_query($user_conn, $query)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Error eliminando dirección']);
}

mysqli_close($user_conn);
?>