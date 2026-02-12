<?php
session_start();
// Cargar config desde raíz
$config = require_once __DIR__ . '/../../config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    echo json_encode(['error' => 'No autenticado']);
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

// Obtener perfil completo
$query = "SELECT * FROM usuarios WHERE id = $user_id";
$result = mysqli_query($user_conn, $query);
$user = mysqli_fetch_assoc($result);

// Obtener direcciones
$addresses_query = "SELECT * FROM user_addresses WHERE user_id = $user_id ORDER BY es_principal DESC";
$addresses_result = mysqli_query($user_conn, $addresses_query);
$addresses = [];
while ($row = mysqli_fetch_assoc($addresses_result)) {
    $addresses[] = $row;
}

// Obtener favoritos
$favorites_query = "SELECT product_id FROM user_favorites WHERE user_id = $user_id";
$favorites_result = mysqli_query($user_conn, $favorites_query);
$favorites = [];
while ($row = mysqli_fetch_assoc($favorites_result)) {
    $favorites[] = $row['product_id'];
}

mysqli_close($user_conn);

echo json_encode([
    'user' => $user,
    'addresses' => $addresses,
    'favorites' => $favorites
]);
?>