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

$tipo = mysqli_real_escape_string($user_conn, $_POST['tipo'] ?? 'casa');
$direccion = mysqli_real_escape_string($user_conn, $_POST['direccion']);
$referencia = mysqli_real_escape_string($user_conn, $_POST['referencia'] ?? '');
$es_principal = isset($_POST['es_principal']) ? 1 : 0;

// Si es principal, quitar principal de otras direcciones
if ($es_principal) {
    mysqli_query($user_conn, "UPDATE user_addresses SET es_principal = 0 WHERE user_id = $user_id");
}

$query = "INSERT INTO user_addresses (user_id, tipo, direccion, referencia, es_principal) 
          VALUES ($user_id, '$tipo', '$direccion', '$referencia', $es_principal)";

if (mysqli_query($user_conn, $query)) {
    echo json_encode(['success' => true, 'id' => mysqli_insert_id($user_conn)]);
} else {
    echo json_encode(['error' => 'Error agregando dirección']);
}

mysqli_close($user_conn);
?>