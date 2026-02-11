<?php
header('Content-Type: application/json');
require_once '../config.php';

// Crear conexión
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Verificar conexión
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Error de conexión: ' . $conn->connect_error
    ]));
}

// Verificar la estructura de la tabla ventas_v2
$sql = "DESCRIBE ventas_v2";
$result = $conn->query($sql);

if (!$result) {
    die(json_encode([
        'success' => false,
        'message' => 'Error al consultar la estructura de la tabla: ' . $conn->error
    ]));
}

$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row;
}

// Cerrar conexión
$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Estructura de la tabla ventas_v2 obtenida correctamente',
    'columns' => $columns
]);
?>