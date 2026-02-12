<?php
session_start();
header('Content-Type: application/json');

$config = require_once __DIR__ . '/../../config.php';

$conn = mysqli_connect(
    $config['ruta11_db_host'],
    $config['ruta11_db_user'],
    $config['ruta11_db_pass'],
    $config['ruta11_db_name']
);

if (!$conn) {
    echo json_encode(['error' => 'Error de conexión a BD']);
    exit();
}

mysqli_set_charset($conn, 'utf8');

// Test 1: Ver qué user_id se está enviando
$user_id = $_GET['user_id'] ?? 'NO_ENVIADO';
echo "User ID recibido: " . $user_id . "\n";

// Test 2: Ver todos los IDs de usuarios
$query = "SELECT id, nombre, kanban_status FROM usuarios LIMIT 5";
$result = mysqli_query($conn, $query);

echo "Usuarios en la tabla:\n";
while ($row = mysqli_fetch_assoc($result)) {
    echo "ID: " . $row['id'] . " - Nombre: " . $row['nombre'] . " - Status: " . ($row['kanban_status'] ?? 'NULL') . "\n";
}

// Test 3: Buscar el usuario específico
if ($user_id !== 'NO_ENVIADO') {
    $search_query = "SELECT * FROM usuarios WHERE id = ?";
    $stmt = mysqli_prepare($conn, $search_query);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $search_result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($search_result);
    
    echo "\nBúsqueda del usuario ID $user_id:\n";
    if ($user) {
        echo "ENCONTRADO - Nombre: " . $user['nombre'] . " - Status: " . ($user['kanban_status'] ?? 'NULL');
    } else {
        echo "NO ENCONTRADO";
    }
}

mysqli_close($conn);
?>