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
$product_id = intval($_POST['product_id']);

// Verificar si ya existe
$check_query = "SELECT id FROM user_favorites WHERE user_id = $user_id AND product_id = $product_id";
$result = mysqli_query($user_conn, $check_query);

if (mysqli_num_rows($result) > 0) {
    // Eliminar favorito
    $query = "DELETE FROM user_favorites WHERE user_id = $user_id AND product_id = $product_id";
    $action = 'removed';
} else {
    // Agregar favorito
    $query = "INSERT INTO user_favorites (user_id, product_id) VALUES ($user_id, $product_id)";
    $action = 'added';
}

if (mysqli_query($user_conn, $query)) {
    echo json_encode(['success' => true, 'action' => $action]);
} else {
    echo json_encode(['error' => 'Error actualizando favorito']);
}

mysqli_close($user_conn);
?>